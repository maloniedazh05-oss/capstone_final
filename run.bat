@echo off

call venv\scripts\activate


pip install --upgrade pip
if not exist venv\Lib\site-packages\pandas (pip install pandas)
if not exist venv\Lib\site-packages\flask (pip install flask)
if not exist venv\Lib\site-packages\statsmodels (pip install statsmodels)


py_backend\forecasting.py

REM Check if node_modules folder exists
if not exist asset (
    mkdir asset
)

if not exist asset\node_modules (
    REM If not, install fontawesome
    echo Installing fontawesome...
    cd asset
    npm install @fortawesome/fontawesome-free
    cd ..
) else (
    REM If yes, check if npm is installed
    where npm >nul 2>nul
    if errorlevel 1 (
        echo npm is not installed. Please download and install npm from https://nodejs.org/en/download before running this project.
        exit /b
    )
)

start http://localhost/capstone_final