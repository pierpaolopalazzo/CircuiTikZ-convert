@echo off
echo inizio conversione
set ORIGINAL_DIR=%CD%
cd /d "%~dp0"
e:\us\core\php82\php.exe convert.php || (
    echo .
    echo Errore in conversione
    echo .
    pause
    exit /b 1
)
echo fine conversione
cd /d "%ORIGINAL_DIR%"

rem pause
