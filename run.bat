@echo off

REM Needs only Node + npm (frontend libs). No Python anymore.

where npm >nul 2>nul
if errorlevel 1 (
    echo npm is not installed. Please download and install npm from https://nodejs.org/en/download before running this project.
    exit /b
)

if not exist assets (
    mkdir assets
)

cd assets
if not exist node_modules\@fortawesome\fontawesome-free (
    echo Installing fontawesome...
    call npm install @fortawesome/fontawesome-free
)
if not exist node_modules\chart.js (
    echo Installing chart.js...
    call npm install chart.js
)
cd ..

start http://localhost/capstone_final
echo RUN the Xampp for localhost to fully work - Apache and MySQL

echo Done. Open http://localhost/capstone_final/login.php in your browser.