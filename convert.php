<?php
// ==================================================================
// INCLUSIONE DELLE LIBRERIE
// ==================================================================

require_once 'libreria_i_o.php';
require_once 'libreria_base.php';


// ==================================================================
// CONFIGURAZIONE
// ==================================================================

const COMPONENT_ALIASES = [
    'V' => 'american voltage source',
    'R' => 'american resistor',
    'resistor' => 'american resistor',
    'L' => 'cute inductor',
    'C' => 'capacitor',
    'I' => 'european current source',
    'current source' => 'european current source',
    'voltage source' => 'american voltage source',
];

// Mappatura dei tipi di nodo speciali
const NODE_TYPE_ALIASES = [
    '*' => 'circ',      // Nodo pieno
    'o' => 'ocirc',     // Nodo vuoto
];

// Fattore di scala per la conversione delle coordinate da LaTeX a JSON.
const LATEX_TO_JSON_SCALE_FACTOR = 37.795;

// Traduce le posizioni delle etichette.
const LABEL_POSITION_MAP = [
    'above' => 'north',
    'below' => 'south',
    'left'  => 'west',
    'right' => 'east',
];


// ==================================================================
// VARIABILI DI STATO E ESECUZIONE
// ==================================================================

$currentPosition = ['x' => 0, 'y' => 0];

// Trova tutti i file che corrispondono al pattern "input-XXX.tex"
$inputFiles = glob('input-*.tex');

if (empty($inputFiles)) {
    die("❌ Errore: Nessun file con pattern 'input-*.tex' trovato nella directory corrente.\n");
}

echo "📁 Trovati " . count($inputFiles) . " file da processare:\n";
foreach ($inputFiles as $file) {
    echo "  - $file\n";
}
echo "\n";

// Processa ogni file trovato
foreach ($inputFiles as $inputFilename) {
    // Genera il nome del file di output corrispondente
    // Da "input-001.tex" a "output-001.json"
    $outputFilename = str_replace(['input-', '.tex'], ['output-', '.json'], $inputFilename);
    
    echo "🔄 Processando: $inputFilename → $outputFilename\n";
    
    // Reset delle variabili per ogni file
    $jsonObjects = [];
    $currentPosition = ['x' => 0, 'y' => 0];
    
    initializeOutput($outputFilename);

    if (!file_exists($inputFilename)) { 
        echo "⚠️  Errore: File '$inputFilename' non trovato, salto al prossimo.\n";
        continue;
    }
    
    $latexCode = file_get_contents($inputFilename);
    if ($latexCode === false) { 
        echo "⚠️  Errore: Impossibile leggere il file '$inputFilename', salto al prossimo.\n";
        continue;
    }

    // Estrae l'intero blocco di codice del circuito
    $circuitContent = extractCircuitikzContent($latexCode);

    if ($circuitContent) {
        // 1. Legge le definizioni \coordinate e crea una mappa al volo
        $dynamicCoordMap = parseCoordinateDefinitions($circuitContent);

        // 2. Estrae tutti i comandi \draw
        $allDrawContents = extractAllDrawContents($circuitContent);

        if ($allDrawContents) {
            foreach ($allDrawContents as $drawContent) {
                // 3. Sostituisce le coordinate nominate con quelle numeriche
                $processedContent = replaceNamedCoords($drawContent, $dynamicCoordMap);
//debugTokenize($processedContent);
//debugTokens($processedContent);
                $tokens = tokenize($processedContent);
                processTokens($tokens, $jsonObjects, $currentPosition);
            }
        }
        
        $jsonOutput = json_encode($jsonObjects, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($outputFilename, $jsonOutput);

        echo "  ✅ Risultato salvato correttamente nel file '$outputFilename'.\n";
    } else {
        $errorJson = json_encode(['error' => 'Nessun blocco \\begin{circuitikz} valido trovato nel file.'], JSON_PRETTY_PRINT);
        file_put_contents($outputFilename, $errorJson);

        echo "  ❌ Errore: nessun blocco circuitikz valido trovato. Dettagli salvati in '$outputFilename'.\n";
    }
}

echo "\n🎉 Processamento completato per tutti i file!\n";

exit;

/**
 * Funzione di debug temporanea
 */
function debugTokenize(string $content): void
{
    echo "=== DEBUG TOKENIZE ===\n";
    echo "Contenuto: '$content'\n";
    $tokens = tokenize($content);
    foreach ($tokens as $i => $token) {
        echo "Token $i: '$token'\n";
    }
    echo "======================\n";
}

/**
 * Funzione di debug per vedere i token
 */
function debugTokens(string $content): void
{
    echo "\n=== DEBUG TOKENS ===\n";
    echo "Contenuto originale:\n$content\n\n";
    
    $tokens = tokenize($content);
    foreach ($tokens as $i => $token) {
        echo "Token $i: '$token'\n";
        
        // Se è un token to[...], proviamo ad estrarre l'etichetta
        if (str_starts_with($token, 'to[')) {
            preg_match('/\[(.*?)\]/', $token, $optionsMatch);
            if (isset($optionsMatch[1])) {
                $optionsStr = $optionsMatch[1];
                echo "  -> Opzioni estratte: '$optionsStr'\n";
                $label = extractLabel($optionsStr);
                echo "  -> Etichetta estratta: '$label'\n";
            }
        }
    }
    echo "====================\n\n";
}
