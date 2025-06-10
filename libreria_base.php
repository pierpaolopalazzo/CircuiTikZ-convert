<?php

/**
 * Controlla se il file di output esiste, lo cancella e verifica la cancellazione.
 * Interrompe lo script in caso di problemi di permessi.
 */
function initializeOutput(string $filename): void
{
    if (file_exists($filename)) {
        if (!unlink($filename)) {
            die("❌ Errore: Impossibile cancellare il vecchio file '$filename'. Controlla i permessi.");
        }
        if (file_exists($filename)) {
            die("❌ Errore: Il file '$filename' non è stato cancellato correttamente.");
        }
    }
}

/**
 * Pulisce e formatta un valore numerico per evitare anomalie.
 */
function cleanNumericValue(float $value): float
{
    // Arrotonda a 3 decimali per evitare errori di floating point
    $rounded = round($value, 3);
    
    // Converte -0 in 0
    if ($rounded == 0) {
        return 0.0;
    }
    
    return $rounded;
}

/**
 * Pulisce un array di coordinate rimuovendo anomalie numeriche.
 */
function cleanCoordinates(array $coords): array
{
    return [
        'x' => cleanNumericValue($coords['x']),
        'y' => cleanNumericValue($coords['y'])
    ];
}

/**
 * Rimuove le etichette vuote dai nodi.
 */
function cleanNodeLabel(array $label): ?array
{
    // Se il valore dell'etichetta è vuoto e la posizione è default, 
    // rimuovi completamente la label
    if (empty($label['value']) && $label['position'] === 'default') {
        return null;
    }
    
    return $label;
}

/**
 * Applica una trasformazione lineare a una coordinata.
 * Moltiplica per il fattore di scala e inverte l'asse Y.
 */
function convertCoordinate(string $value, string $axis): float
{
    $floatValue = (float) $value;
    $scaledValue = $floatValue * LATEX_TO_JSON_SCALE_FACTOR;
    if ($axis === 'y') {
        $scaledValue = -1 * $scaledValue; // Inverte l'asse Y
    }
    
    // Pulisce il valore per evitare anomalie
    return cleanNumericValue($scaledValue);
}

/**
 * Gestisce i connettori speciali come -o, *-*, o-o, etc.
 * Restituisce un array con le informazioni sui nodi da creare.
 */
function parseSpecialConnector(string $optionsStr): array
{
    $result = [
        'isSpecial' => false,
        'startNode' => null,
        'endNode' => null
    ];
    
    // Pattern per i connettori speciali
    $patterns = [
        '/^short,\s*-o$/' => ['start' => null, 'end' => 'ocirc'],
        '/^short,\s*o-$/' => ['start' => 'ocirc', 'end' => null],
        '/^short,\s*o-o$/' => ['start' => 'ocirc', 'end' => 'ocirc'],
        '/^short,\s*\*-\*$/' => ['start' => 'circ', 'end' => 'circ'],
        '/^short,\s*\*-o$/' => ['start' => 'circ', 'end' => 'ocirc'],
        '/^short,\s*o-\*$/' => ['start' => 'ocirc', 'end' => 'circ'],
        '/^-o$/' => ['start' => null, 'end' => 'ocirc'],
        '/^o-$/' => ['start' => 'ocirc', 'end' => null],
        '/^o-o$/' => ['start' => 'ocirc', 'end' => 'ocirc'],
        '/^\*-\*$/' => ['start' => 'circ', 'end' => 'circ'],
        '/^\*-o$/' => ['start' => 'circ', 'end' => 'ocirc'],
        '/^o-\*$/' => ['start' => 'ocirc', 'end' => 'circ'],
    ];
    
    foreach ($patterns as $pattern => $nodes) {
        if (preg_match($pattern, trim($optionsStr))) {
            $result['isSpecial'] = true;
            $result['startNode'] = $nodes['start'];
            $result['endNode'] = $nodes['end'];
            break;
        }
    }
    
    return $result;
}

/**
 * Crea un nodo semplice senza etichetta
 */
function createSimpleNode(string $nodeType, array $position, array &$jsonObjects): void
{
    $cleanPosition = cleanCoordinates($position);
    
    $fullName = COMPONENT_ALIASES[$nodeType] ?? $nodeType;
    $componentId = 'node_' . str_replace(' ', '-', $fullName);
    
    $jsonObjects[] = [
        'type' => 'node',
        'id' => $componentId,
        'position' => $cleanPosition
    ];
}

/**
 * Costruisce e aggiunge un oggetto "wire". Questo elemento non ha etichette.
 */
function buildWireComponent(array $start, array $end, array &$jsonObjects, ?array &$pendingLabel): void
{
    // Un filo consuma un'etichetta in sospeso per evitare che venga usata
    // dal componente successivo, ma non la include nel suo output.
    if ($pendingLabel) {
        $pendingLabel = null;
    }
    
    // Pulisce le coordinate
    $cleanStart = cleanCoordinates($start);
    $cleanEnd = cleanCoordinates($end);
    
    // Non aggiunge wire se start e end sono identici
    if ($cleanStart['x'] == $cleanEnd['x'] && $cleanStart['y'] == $cleanEnd['y']) {
        return;
    }
    
    $jsonObjects[] = [
        'type' => 'wire',
        'start' => $cleanStart,
        'segments' => [['endPoint' => $cleanEnd, 'direction' => '-|']],
    ];
}

/**
 * Costruisce e aggiunge un oggetto "path" (componente).
 * La sua etichetta contiene solo 'value' e 'distance'.
 */
function buildPathComponent(string $token, array $start, array $end, array &$jsonObjects, ?array &$pendingLabel): void
{
    preg_match('/\[(.*?)\]/', $token, $optionsMatch);
    $optionsStr = $optionsMatch[1];
    
    // Controlla se è un connettore speciale
    $specialConnector = parseSpecialConnector($optionsStr);
    
    if ($specialConnector['isSpecial']) {
        // Crea il wire
        buildWireComponent($start, $end, $jsonObjects, $pendingLabel);
        
        // Crea i nodi alle estremità se necessario
        if ($specialConnector['startNode']) {
            createSimpleNode($specialConnector['startNode'], $start, $jsonObjects);
        }
        if ($specialConnector['endNode']) {
            createSimpleNode($specialConnector['endNode'], $end, $jsonObjects);
        }
        
        return;
    }
    
    // Dividi le opzioni per virgola ma rispetta le parentesi graffe e i $
    $options = parseOptions($optionsStr);
    
    // Il primo elemento che NON contiene = è il tipo di componente
    // OPPURE se il primo elemento è del tipo "R=...", "L=...", "C=...", usa la parte prima di =
    $typeKey = '';
    foreach ($options as $option) {
        $option = trim($option);
        if (!str_contains($option, '=') && !empty($option)) {
            $typeKey = $option;
            break;
        } elseif (preg_match('/^([RLC]|resistor|inductor|capacitor)=/', $option, $match)) {
            // Se è del tipo R=..., L=..., C=..., usa la parte prima di =
            $typeKey = $match[1];
            break;
        }
    }
    
    // Se non abbiamo trovato un tipo, usa il primo elemento
    if (empty($typeKey) && !empty($options)) {
        $firstOption = trim($options[0]);
        if (str_contains($firstOption, '=')) {
            $parts = explode('=', $firstOption, 2);
            $typeKey = trim($parts[0]);
        } else {
            $typeKey = $firstOption;
        }
    }

    if ($typeKey === 'short') {
        buildWireComponent($start, $end, $jsonObjects, $pendingLabel);
        return;
    }

    $fullName = COMPONENT_ALIASES[$typeKey] ?? $typeKey;
    $componentId = 'path_' . str_replace(' ', '-', $fullName);

    $label = [
        'value' => extractLabel($optionsStr),
        'distance' => '0.12cm'
    ];

    // Se c'è un'etichetta in sospeso e questo componente non ne ha una sua,
    // ne usa il valore, ma senza includere anchor/position.
    if ($pendingLabel && empty($label['value'])) {
        $label['value'] = $pendingLabel['value'];
        $pendingLabel = null;
    }

    // Pulisce le coordinate
    $cleanStart = cleanCoordinates($start);
    $cleanEnd = cleanCoordinates($end);

    $jsonObjects[] = [
        'type' => 'path',
        'id' => $componentId,
        'start' => $cleanStart,
        'end' => $cleanEnd,
        'label' => $label,
    ];
}

/**
 * Costruisce un oggetto "node".
 * La sua etichetta è completa di 'anchor' e 'position'.
 * Può restituire un'etichetta "in sospeso" se il nodo è solo un segnaposto.
 */
function buildNodeComponent(string $token, array $position): ?array
{
    // Regex migliorata che gestisce correttamente le parentesi graffe annidate
    if (!preg_match('/node\s*\[([^\]]*)\]\s*(?:\{(.*)\})?$/s', $token, $matches)) {
        return null; // Formato del token non valido
    }
    
    $optionsStr = $matches[1] ?? '';
    $labelInBraces = isset($matches[2]) ? trim($matches[2]) : ''; 

    // Controlla se è un nodo con connettore speciale
    $specialConnector = parseSpecialConnector($optionsStr);
    
    if ($specialConnector['isSpecial']) {
        // Per i nodi speciali, restituisce un array speciale che verrà gestito in processTokens
        return [
            'type' => 'special_node',
            'startNode' => $specialConnector['startNode'],
            'endNode' => $specialConnector['endNode'],
            'position' => $position
        ];
    }

    // Pulisce l'etichetta dai caratteri $ se presenti
    if (!empty($labelInBraces)) {
        // Rimuove $ dall'inizio e dalla fine
        if (str_starts_with($labelInBraces, '$') && str_ends_with($labelInBraces, '$')) {
            $labelInBraces = substr($labelInBraces, 1, -1);
        }
    }

    $options = array_map('trim', explode(',', $optionsStr));
    
    $nodeType = 'empty'; 
    $labelValue = $labelInBraces;
    $labelPosition = 'default';
    $hasVisualType = false;

    foreach ($options as $option) {
        if (isset(LABEL_POSITION_MAP[$option])) {
            // È una posizione (es. 'right'), non un tipo di nodo
            $labelPosition = LABEL_POSITION_MAP[$option];
        } elseif (str_contains($option, '=')) {
            // È un'opzione chiave=valore come 'label=...'
            list($key, $value) = explode('=', $option, 2);
            if ($key === 'label') {
                // Gestisce correttamente la separazione posizione:valore
                if (str_contains($value, ':')) {
                    $labelParts = explode(':', $value, 2);
                    $posKey = trim($labelParts[0]);
                    $labelPosition = LABEL_POSITION_MAP[$posKey] ?? $posKey;
                    $labelValue = trim($labelParts[1]);
                } else {
                    // Se non c'è ':', tutto il valore è l'etichetta
                    $labelValue = extractLabel('label='.$value);
                }
                
                // Rimuove eventuali caratteri di delimitazione ($, {})
                while (true) {
                    $initialValue = $labelValue;
                    if (str_starts_with($labelValue, '{') && str_ends_with($labelValue, '}')) {
                        $labelValue = substr($labelValue, 1, -1);
                    }
                    if (str_starts_with($labelValue, '$') && str_ends_with($labelValue, '$')) {
                        $labelValue = substr($labelValue, 1, -1);
                    }
                    if ($labelValue === $initialValue) {
                        break;
                    }
                }
            }
        } else if (!empty($option) && $option !== 'short') { // Ignora 'short'
            // È un tipo di nodo - prima controlla gli alias speciali
            if (isset(NODE_TYPE_ALIASES[$option])) {
                $nodeType = NODE_TYPE_ALIASES[$option];
            } else {
                $nodeType = $option;
            }
            $hasVisualType = true;
        }
    }

    // Se abbiamo un'etichetta ma nessun tipo di nodo specificato, 
    // usiamo ocirc come predefinito
    if (!$hasVisualType && !empty($labelValue)) {
        $nodeType = 'ocirc';
        $hasVisualType = true;
    }

    // Se alla fine di tutto il nodo è ancora 'empty', non va disegnato.
    if ($nodeType === 'empty') {
       return null;
    }

    // Costruisce l'oggetto etichetta completo, come richiesto per i nodi.
    $label = [
        'value' => $labelValue,
        'anchor' => 'default',
        'position' => $labelPosition,
        'distance' => '0.12cm'
    ];
    
    // Pulisce l'etichetta se vuota
    $cleanLabel = cleanNodeLabel($label);

    $fullName = COMPONENT_ALIASES[$nodeType] ?? $nodeType;
    $componentId = 'node_' . str_replace(' ', '-', $fullName);

    // Pulisce le coordinate
    $cleanPosition = cleanCoordinates($position);

    $result = [
        'type' => 'node',
        'id' => $componentId,
        'position' => $cleanPosition,
    ];
    
    // Aggiunge la label solo se non è vuota
    if ($cleanLabel !== null) {
        $result['label'] = $cleanLabel;
    }
    
    return $result;
}

/**
 * Processa la sequenza di token per costruire il circuito.
 * Usa un ciclo 'while' per gestire in modo affidabile l'avanzamento.
 */
function processTokens(array $tokens, array &$jsonObjects, array &$currentPosition): void
{
    $pendingLabel = null;
    $i = 0;
    $tokenCount = count($tokens);

    while ($i < $tokenCount) {
        $token = $tokens[$i];

        // Caso 1: Il token è una coordinata (un punto di partenza)
        if (preg_match('/^\((.*?),(.*?)\)$/', $token, $coords)) {
            $x_converted = convertCoordinate(trim($coords[1]), 'x');
            $y_converted = convertCoordinate(trim($coords[2]), 'y');
            $currentPosition = ['x' => $x_converted, 'y' => $y_converted];
            $i++;
            continue;
        }

        // Caso 2: Il token è un nodo
        if (str_starts_with($token, 'node[')) {
            $result = buildNodeComponent($token, $currentPosition);
            if ($result) {
                // Se è un nodo speciale, crea i nodi componenti
                if (isset($result['type']) && $result['type'] === 'special_node') {
                    if ($result['startNode']) {
                        createSimpleNode($result['startNode'], $result['position'], $jsonObjects);
                    }
                    if ($result['endNode']) {
                        createSimpleNode($result['endNode'], $result['position'], $jsonObjects);
                    }
                } else {
                    // È un nodo normale
                    if ($pendingLabel && empty($result['label']['value'])) {
                        $result['label'] = $pendingLabel;
                        $pendingLabel = null;
                    }
                    $jsonObjects[] = $result;
                }
            }
            $i++;
            continue;
        }
        
        // Caso 3: Il token è un segmento di percorso (to[...] o --)
        $nextToken = $tokens[$i + 1] ?? null;
        
        // Controlla se il prossimo token è una coordinata
        if ($nextToken && preg_match('/^\((.*?),(.*?)\)$/', $nextToken, $endCoords)) {
            $startPoint = $currentPosition;
            $end_x_converted = convertCoordinate(trim($endCoords[1]), 'x');
            $end_y_converted = convertCoordinate(trim($endCoords[2]), 'y');
            $endPoint = ['x' => $end_x_converted, 'y' => $end_y_converted];

            if (str_starts_with($token, 'to[')) {
                buildPathComponent($token, $startPoint, $endPoint, $jsonObjects, $pendingLabel);
            } elseif ($token === '--') {
                buildWireComponent($startPoint, $endPoint, $jsonObjects, $pendingLabel);
            }

            $currentPosition = $endPoint;
            $i += 2; // Avanza oltre il token corrente e la sua coordinata finale
            
            // AGGIUNTA: Controlla se c'è un nodo subito dopo la coordinata
            $nodeToken = $tokens[$i] ?? null;
            if ($nodeToken && str_starts_with($nodeToken, 'node[')) {
                $result = buildNodeComponent($nodeToken, $currentPosition);
                if ($result) {
                    // Se è un nodo speciale, crea i nodi componenti
                    if (isset($result['type']) && $result['type'] === 'special_node') {
                        if ($result['startNode']) {
                            createSimpleNode($result['startNode'], $result['position'], $jsonObjects);
                        }
                        if ($result['endNode']) {
                            createSimpleNode($result['endNode'], $result['position'], $jsonObjects);
                        }
                    } else {
                        // È un nodo normale
                        if ($pendingLabel && empty($result['label']['value'])) {
                            $result['label'] = $pendingLabel;
                            $pendingLabel = null;
                        }
                        $jsonObjects[] = $result;
                    }
                }
                $i++; // Avanza oltre il token del nodo
            }
            
            continue;
        }

        // Se un token non corrisponde a nessuno dei casi, lo saltiamo per sicurezza
        $i++;
    }
}