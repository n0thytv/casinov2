@echo off
setlocal EnableExtensions

rem ====== Anti-recursion: chemin de ce script
set "SELF=%~f0"

rem ====== Résoudre le dossier cible
if "%~1"=="" (
  set "TARGET=%CD%"
) else (
  set "TARGET=%~f1"
)
echo [INFO] Dossier cible: "%TARGET%"

if not exist "%TARGET%" (
  echo [INFO] Création du dossier...
  md "%TARGET%" || (echo [ERREUR] Impossible de créer "%TARGET%" & goto :fail)
)

pushd "%TARGET%" || (echo [ERREUR] Impossible d'entrer dans "%TARGET%" & goto :fail)

rem ====== Trouver le vrai 'claude' dans le PATH (en ignorant ce script)
set "CLAUDE_CMD="
for /f "delims=" %%I in ('where claude 2^>nul') do (
  if /I not "%%~fI"=="%SELF%" (
    set "CLAUDE_CMD=%%~fI"
    goto :found
  )
)

echo [ERREUR] 'claude' introuvable dans le PATH ou conflit de nom.
echo [ASTUCE] Ne nommez pas ce fichier 'claude.bat'/'claude.cmd'. Renommez-le 'launch_claude.bat'.
goto :fail

:found
echo [INFO] Claude trouvé: %CLAUDE_CMD%
echo.

echo [INFO] Version de Claude:
call "%CLAUDE_CMD%" --version
echo.

echo [INFO] Lancement: "%CLAUDE_CMD%" --dangerously-skip-permissions
echo -------------------------------------------------------
call "%CLAUDE_CMD%" --dangerously-skip-permissions
set "RC=%ERRORLEVEL%"
echo -------------------------------------------------------
echo [INFO] Code de sortie: %RC%

goto :done

:fail
set "RC=1"

:done
echo.
echo Appuyez sur une touche pour fermer...
pause >nul
popd >nul 2>&1
exit /b %RC%
