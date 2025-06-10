# CircuiTikZ Convert

**CircuiTikZ Convert** è uno script PHP che converte file LaTeX contenenti circuiti disegnati con CircuiTikZ in file JSON strutturati, utili per ulteriori elaborazioni, visualizzazioni o importazioni in altri strumenti.

## Funzionalità

- Estrae automaticamente tutti i file `input-XXX.tex` nella cartella.
- Converte i circuiti CircuiTikZ in formato JSON.
- Supporta alias per componenti, nodi speciali e posizioni etichette.
- Gestione robusta di errori e input non standard.
- Output in file `output-XXX.json` corrispondenti.

## Requisiti

- **PHP 8.2** o superiore (modificare il percorso in `convert.bat` se necessario).
- Sistema operativo: Windows (per lanciare lo script batch) o qualsiasi sistema con PHP da terminale.

## Struttura del progetto

- `convert.php` — Script principale di conversione.
- `libreria_base.php` — Funzioni di supporto per parsing e costruzione JSON.
- `libreria_i_o.php` — Funzioni di supporto per estrazione e manipolazione dei dati LaTeX.
- `convert.bat` — Script batch per esecuzione rapida su Windows.
- `input-XXX.tex` — File di input LaTeX (uno o più).
- `output-XXX.json` — File di output generati.
- `.gitignore` — File di esclusione standard.

## Utilizzo

1. **Prepara i file di input**  
   Inserisci nella cartella i file LaTeX da convertire, seguendo il pattern `input-000.tex`, `input-001.tex`, ecc.

2. **Esegui la conversione**  
   - Su Windows:  
     Fai doppio clic su `convert.bat`  
     _oppure_  
     Apri il terminale nella cartella e lancia:
     ```
     convert.bat
     ```
   - Su altri sistemi:  
     Lancia direttamente lo script PHP:
     ```
     php convert.php
     ```

3. **Risultato**  
   Verranno generati i file `output-XXX.json` per ogni file di input trovato.

## Personalizzazione

- Puoi modificare gli alias dei componenti e delle etichette direttamente in `convert.php`.
- Le funzioni di parsing sono estendibili per supportare nuovi tipi di componenti o sintassi.

## Esempio di input

```latex
\begin{circuitikz}
    \draw (0,0) to[R, l=$R_1$] (2,0);
    \draw (2,0) to[C, l=$C_1$] (2,2);
\end{circuitikz}
```

## Esempio di output

```json
[
  {
    "type": "component",
    "id": "american-resistor",
    "position": {"x":0,"y":0},
    "end": {"x":75.59,"y":0},
    "label": {"value":"R_1","position":"north"}
  },
  ...
]
```

## Note

- I messaggi di log sono in italiano.
- Se vuoi aggiungere nuovi tipi di componenti, modifica la costante `COMPONENT_ALIASES` in `convert.php`.
- Per problemi di permessi sui file, assicurati di avere i diritti di scrittura nella cartella.

## Licenza

Questo progetto è distribuito sotto la licenza MIT. Vedi il file [LICENSE](LICENSE) per i dettagli.

## Credits

- **CircuiTikZ Designer**: [CircuiTikZ Designer](https://circuit2tikz.tf.fau.de/designer/) è stato utilizzato per la progettazione dei circuiti LaTeX.
- **CircuiTikZ**: Il pacchetto LaTeX [CircuiTikZ](https://www.ctan.org/pkg/circuitikz) è stato fondamentale per la creazione dei circuiti.

---

# CircuiTikZ Convert

**CircuiTikZ Convert** is a PHP script that converts LaTeX files containing circuits drawn with CircuiTikZ into structured JSON files, useful for further processing, visualization, or importing into other tools.

## Features

- Automatically extracts all `input-XXX.tex` files in the folder.
- Converts CircuiTikZ circuits to JSON format.
- Supports aliases for components, special nodes, and label positions.
- Robust error handling and non-standard input management.
- Outputs corresponding `output-XXX.json` files.

## Requirements

- **PHP 8.2** or higher (modify the path in `convert.bat` if necessary).
- Operating system: Windows (to run the batch script) or any system with PHP from the terminal.

## Project Structure

- `convert.php` — Main conversion script.
- `libreria_base.php` — Support functions for parsing and JSON construction.
- `libreria_i_o.php` — Support functions for LaTeX data extraction and manipulation.
- `convert.bat` — Batch script for quick execution on Windows.
- `input-XXX.tex` — LaTeX input files (one or more).
- `output-XXX.json` — Generated output files.
- `.gitignore` — Standard exclusion file.

## Usage

1. **Prepare input files**  
   Place the LaTeX files to convert in the folder, following the pattern `input-000.tex`, `input-001.tex`, etc.

2. **Run the conversion**  
   - On Windows:  
     Double-click `convert.bat`  
     _or_  
     Open the terminal in the folder and run:
     ```
     convert.bat
     ```
   - On other systems:  
     Run the PHP script directly:
     ```
     php convert.php
     ```

3. **Result**  
   `output-XXX.json` files will be generated for each input file found.

## Customization

- You can modify component and label aliases directly in `convert.php`.
- Parsing functions are extensible to support new component types or syntax.

## Input Example

```latex
\begin{circuitikz}
    \draw (0,0) to[R, l=$R_1$] (2,0);
    \draw (2,0) to[C, l=$C_1$] (2,2);
\end{circuitikz}
```

## Output Example

```json
[
  {
    "type": "component",
    "id": "american-resistor",
    "position": {"x":0,"y":0},
    "end": {"x":75.59,"y":0},
    "label": {"value":"R_1","position":"north"}
  },
  ...
]
```

## Notes

- Log messages are in Italian.
- To add new component types, modify the `COMPONENT_ALIASES` constant in `convert.php`.
- For file permission issues, ensure you have write rights in the folder.

## License

This project is distributed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Credits

- **CircuiTikZ Designer**: [CircuiTikZ Designer](https://circuit2tikz.tf.fau.de/designer/) was used for LaTeX circuit design.
- **CircuiTikZ**: The [CircuiTikZ](https://www.ctan.org/pkg/circuitikz) LaTeX package was essential for circuit creation. 
