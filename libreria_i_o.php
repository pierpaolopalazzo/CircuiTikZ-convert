<?php

/**
 * Estrae l'intero contenuto del blocco \begin{circuitikz} o \begin{tikzpicture}.
 */
function extractCircuitikzContent(string $latexCode): ?string
{
    if (preg_match('/\\\\begin\{(circuitikz|tikzpicture)\}(.*?)\\\\end\{\1\}/s', $latexCode, $matches)) {
        return $matches[2];
    }
    return null;
}

/**
 * Analizza il testo e crea una mappa di tutte le coordinate definite con \coordinate.
 */
function parseCoordinateDefinitions(string $content): array
{
    $coordMap = [];
    $pattern = '/\\\\coordinate\s*\((.*?)\)\s*at\s*\((.*?)\);/s';
    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $name = trim($match[1]);
            $value = '('.trim($match[2]).')';
            $coordMap[$name] = $value;
        }
    }
    return $coordMap;
}

/**
 * Sostituisce le coordinate nominate (es. (A)) con i loro valori (es. (0,4)).
 */
function replaceNamedCoords(string $content, array $coordMap): string
{
    foreach ($coordMap as $name => $value) {
        $content = str_replace('('.$name.')', $value, $content);
    }
    return $content;
}

/**
 * Estrae TUTTI i comandi \draw e \node da un blocco di testo, ignorando quelli commentati e quelli con frecce.
 */
function extractAllDrawContents(string $content): ?array
{
    // Prima rimuove i commenti dal contenuto
    $cleanContent = removeComments($content);
    
    $allCommands = [];
    
    // Estrae i comandi \draw, ma ignora quelli con frecce
    if (preg_match_all('/\\\\draw(\[.*?\])?(.*?);/s', $cleanContent, $drawMatches, PREG_SET_ORDER)) {
        foreach ($drawMatches as $drawMatch) {
            $options = $drawMatch[1] ?? '';
            $content = $drawMatch[2] ?? '';
            
            // Ignora i comandi \draw che contengono frecce negli options
            if (preg_match('/\[.*?(<-|->|<->).*?\]/', $options)) {
                continue; // Salta questo draw
            }
            
            $allCommands[] = trim($content);
        }
    }
    
    // Estrae i comandi \node standalone con regex più flessibile
    if (preg_match_all('/\\\\node\s*(\[[^\]]*\])?\s*at\s*\(([^)]+)\)\s*(\[[^\]]*\])?\s*\{([^}]*)\}\s*;/s', $cleanContent, $nodeMatches, PREG_SET_ORDER)) {
        foreach ($nodeMatches as $nodeMatch) {
            $coordinates = trim($nodeMatch[2]);
            $firstOptions = isset($nodeMatch[1]) ? trim($nodeMatch[1]) : '';
            $secondOptions = isset($nodeMatch[3]) ? trim($nodeMatch[3]) : '';
            $label = trim($nodeMatch[4]);
            
            // Le opzioni possono essere prima o dopo "at"
            $options = !empty($firstOptions) ? $firstOptions : $secondOptions;
            
            // Rimuove le parentesi quadre dagli options se presenti
            if (str_starts_with($options, '[') && str_ends_with($options, ']')) {
                $options = substr($options, 1, -1);
            }
            
            // Se non ci sono opzioni, usa una posizione di default
            if (empty($options)) {
                $options = 'above';
            }
            
            // Costruisce un contenuto equivalente a un draw per il processamento
            $nodeContent = "($coordinates) node[$options]{{$label}}";
            $allCommands[] = $nodeContent;
        }
    }
    
    // Restituisce i contenuti se ci sono comandi
    if (!empty($allCommands)) {
        return $allCommands;
    }
    
    return null;
}

/**
 * Rimuove i commenti LaTeX dal contenuto.
 * Un commento inizia con % e continua fino alla fine della riga.
 */
function removeComments(string $content): string
{
    $lines = explode("\n", $content);
    $cleanLines = [];
    
    foreach ($lines as $line) {
        // Trova la posizione del primo % non preceduto da \
        $commentPos = false;
        $length = strlen($line);
        
        for ($i = 0; $i < $length; $i++) {
            if ($line[$i] === '%') {
                // Controlla se il % è preceduto da \
                if ($i > 0 && $line[$i-1] === '\\') {
                    continue; // È un \%, non un commento
                }
                $commentPos = $i;
                break;
            }
        }
        
        if ($commentPos !== false) {
            // Rimuove tutto dal % in poi
            $cleanLine = substr($line, 0, $commentPos);
            // Rimuove spazi bianchi finali
            $cleanLine = rtrim($cleanLine);
            if (!empty($cleanLine)) {
                $cleanLines[] = $cleanLine;
            }
        } else {
            // Nessun commento, mantiene la riga come è (se non vuota)
            $trimmedLine = trim($line);
            if (!empty($trimmedLine)) {
                $cleanLines[] = $line;
            }
        }
    }
    
    return implode("\n", $cleanLines);
}

/**
 * ✅ FUNZIONE CORRETTA
 * Scompone il contenuto del draw in una lista di "token" usando un approccio carattere per carattere.
 */
function tokenize(string $content): array
{
    $tokens = [];
    $length = strlen($content);
    $i = 0;
    
    while ($i < $length) {
        // Salta spazi bianchi
        while ($i < $length && ctype_space($content[$i])) {
            $i++;
        }
        
        if ($i >= $length) break;
        
        // Coordina (x,y)
        if ($content[$i] === '(') {
            $start = $i;
            $depth = 0;
            while ($i < $length) {
                if ($content[$i] === '(') $depth++;
                if ($content[$i] === ')') $depth--;
                $i++;
                if ($depth === 0) break;
            }
            $tokens[] = substr($content, $start, $i - $start);
            continue;
        }
        
        // Token --
        if ($i < $length - 1 && substr($content, $i, 2) === '--') {
            $tokens[] = '--';
            $i += 2;
            continue;
        }
        
        // Token che inizia con 'node'
        if ($i < $length - 3 && substr($content, $i, 4) === 'node') {
            $start = $i;
            $i += 4; // salta 'node'
            
            // Salta spazi
            while ($i < $length && ctype_space($content[$i])) {
                $i++;
            }
            
            // Cerca le parentesi quadre [...]
            if ($i < $length && $content[$i] === '[') {
                $depth = 0;
                while ($i < $length) {
                    if ($content[$i] === '[') $depth++;
                    if ($content[$i] === ']') $depth--;
                    $i++;
                    if ($depth === 0) break;
                }
            }
            
            // Salta spazi
            while ($i < $length && ctype_space($content[$i])) {
                $i++;
            }
            
            // Cerca le parentesi graffe {...}
            if ($i < $length && $content[$i] === '{') {
                $depth = 0;
                while ($i < $length) {
                    if ($content[$i] === '{') $depth++;
                    if ($content[$i] === '}') $depth--;
                    $i++;
                    if ($depth === 0) break;
                }
            }
            
            $tokens[] = substr($content, $start, $i - $start);
            continue;
        }
        
        // Token 'to[...]' - VERSIONE COMPLETAMENTE RISCRITTA
        if ($i < $length - 1 && substr($content, $i, 2) === 'to') {
            $start = $i;
            $i += 2; // salta 'to'
            
            // Salta spazi
            while ($i < $length && ctype_space($content[$i])) {
                $i++;
            }
            
            // Deve iniziare con [
            if ($i < $length && $content[$i] === '[') {
                $squareBracketDepth = 0;
                $curlyBraceDepth = 0;
                $inDollar = false;
                $escapeNext = false;
                
                while ($i < $length) {
                    $char = $content[$i];
                    
                    if ($escapeNext) {
                        // Carattere escaped, salta
                        $escapeNext = false;
                    } elseif ($char === '\\') {
                        // Prossimo carattere sarà escaped
                        $escapeNext = true;
                    } elseif ($char === '$') {
                        // Toggle stato dollar
                        $inDollar = !$inDollar;
                    } elseif (!$inDollar) {
                        // Solo se non siamo dentro i $, consideriamo le parentesi
                        if ($char === '[') {
                            $squareBracketDepth++;
                        } elseif ($char === ']') {
                            $squareBracketDepth--;
                        } elseif ($char === '{') {
                            $curlyBraceDepth++;
                        } elseif ($char === '}') {
                            $curlyBraceDepth--;
                        }
                    }
                    
                    $i++;
                    
                    // Usciamo quando tutte le parentesi quadre sono chiuse e non siamo in dollar
                    if ($squareBracketDepth === 0 && $curlyBraceDepth === 0 && !$inDollar) {
                        break;
                    }
                }
            }
            
            $tokens[] = substr($content, $start, $i - $start);
            continue;
        }
        
        // Se non corrisponde a nessun pattern, salta il carattere
        $i++;
    }
    
    return array_map('trim', $tokens);
}

/**
 * Divide le opzioni rispettando le parentesi graffe e i caratteri $
 */
function parseOptions(string $optionsStr): array
{
    $options = [];
    $current = '';
    $inDollar = false;
    $inBraces = 0;
    $len = strlen($optionsStr);
    
    for ($i = 0; $i < $len; $i++) {
        $char = $optionsStr[$i];
        
        if ($char === '\\' && $i + 1 < $len) {
            // Carattere escaped
            $current .= $char . $optionsStr[$i + 1];
            $i++; // salta il prossimo carattere
        } elseif ($char === '$') {
            $inDollar = !$inDollar;
            $current .= $char;
        } elseif ($char === '{') {
            $inBraces++;
            $current .= $char;
        } elseif ($char === '}') {
            $inBraces--;
            $current .= $char;
        } elseif ($char === ',' && !$inDollar && $inBraces === 0) {
            // Virgola che separa le opzioni
            $options[] = trim($current);
            $current = '';
        } else {
            $current .= $char;
        }
    }
    
    // Aggiungi l'ultima opzione
    if (!empty(trim($current))) {
        $options[] = trim($current);
    }
    
    return $options;
}

/**
 * Estrae un'etichetta da opzioni come l=, R=, v=, i= e rimuove i '$' e '{}' esterni.
 */
function extractLabel(string $optionsStr): string
{
    // Pattern per trovare diverse etichette possibili, in ordine di priorità
    $patterns = [
        '/\bl=(.*)/',           // l=...
        '/\bR=(.*)/',           // R=...
        '/\bL=(.*)/',           // L=...
        '/\bC=(.*)/',           // C=...
        '/\bv=(.*)/',           // v=...
        '/\bi[>^_]*=(.*)/',     // i=, i>=, i^=, i_=, etc.
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $optionsStr, $labelMatch)) {
            $afterEquals = trim($labelMatch[1]);
            
            // Se inizia con $, trova il $ di chiusura corrispondente
            if (str_starts_with($afterEquals, '$')) {
                $pos = 1; // inizia dopo il primo $
                $len = strlen($afterEquals);
                
                while ($pos < $len) {
                    $char = $afterEquals[$pos];
                    
                    // Se troviamo \, salta il prossimo carattere
                    if ($char === '\\' && $pos + 1 < $len) {
                        $pos += 2; // salta \ e il carattere successivo
                        continue;
                    }
                    
                    // Se troviamo $, abbiamo trovato la fine
                    if ($char === '$') {
                        $value = substr($afterEquals, 0, $pos + 1);
                        break;
                    }
                    
                    $pos++;
                }
            } elseif (str_starts_with($afterEquals, '{')) {
                // Se inizia con {, trova la } corrispondente
                $braceCount = 0;
                $pos = 0;
                $len = strlen($afterEquals);
                
                while ($pos < $len) {
                    if ($afterEquals[$pos] === '{') {
                        $braceCount++;
                    } elseif ($afterEquals[$pos] === '}') {
                        $braceCount--;
                        if ($braceCount === 0) {
                            $value = substr($afterEquals, 0, $pos + 1);
                            break;
                        }
                    }
                    $pos++;
                }
            } else {
                // Se non inizia con $ o {, prendi fino alla prima virgola o alla fine
                $commaPos = strpos($afterEquals, ',');
                if ($commaPos !== false) {
                    $value = substr($afterEquals, 0, $commaPos);
                } else {
                    $value = $afterEquals;
                }
            }
            
            if (!isset($value)) {
                $value = $afterEquals;
            }
            
            $value = trim($value);
            
            // Rimuove i delimitatori esterni
            while (true) {
                $initialValue = $value;
                if (str_starts_with($value, '{') && str_ends_with($value, '}')) {
                    $value = substr($value, 1, -1);
                }
                if (str_starts_with($value, '$') && str_ends_with($value, '$')) {
                    $value = substr($value, 1, -1);
                }
                if ($value === $initialValue) {
                    break;
                }
            }
            
            return $value;
        }
    }
    
    return '';
}