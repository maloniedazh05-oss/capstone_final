REQUIREMENTS(Once download and installed - Works Completely offline/local): Windows >= 10

- Node JS:
https://nodejs.org/en/download
#Direct dl: https://nodejs.org/dist/v24.21.0/node-v24.21.0-x64.msi

- Xampp: https://www.apachefriends.org/
#Run as admin when opening the program.
Step 1:
Copy - "xampp path directory/php" folder.
Step 2:
Ctrl + R then enter: 
rundll32.exe sysdm.cpl,EditEnvironmentVariables
Step 3:
- User variables - Path - Edit - New - Paste "xampp path directory/php" Path - Ok.

=======================================
------------- DATABASE ----------------
=======================================
-- Latest DB (fresh install: run everything below in phpMyAdmin):
-- Existing DB: run only the MIGRATION blocks in `database_query`.

CREATE DATABASE IF NOT EXISTS rural_urban;
USE rural_urban;

CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(30),
    user VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100),
    pass VARCHAR(255),
    admin BOOLEAN UNIQUE DEFAULT NULL,
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active'
);

CREATE TABLE total (
    total_stock DECIMAL(10,2) NOT NULL DEFAULT 0.00
);

CREATE TABLE inventory (
    prod_id VARCHAR(15) PRIMARY KEY,
    product VARCHAR(30) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    unit VARCHAR(10) NOT NULL,
    status VARCHAR(50) DEFAULT 'Ongoing',
    description TINYTEXT,
    stock_in DECIMAL(10,2),
    stock_out DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE production (
    production_id INT PRIMARY KEY AUTO_INCREMENT,
    batch_id VARCHAR(7) NOT NULL,
    production_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    item VARCHAR(15),
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(10) NOT NULL,
    status VARCHAR(30) DEFAULT 'Ongoing',
    receiver VARCHAR(50),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user VARCHAR(50) NOT NULL,
    action VARCHAR(30) NOT NULL,
    ref_id VARCHAR(15),
    product VARCHAR(50),
    quantity DECIMAL(10,2),
    unit VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE forecasting_monthly (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product VARCHAR(50) NOT NULL,
    months_used INT NOT NULL,
    alpha DECIMAL(4,2) NOT NULL,
    forecast_qty DECIMAL(10,2) NOT NULL,
    forecast_month CHAR(7) NOT NULL,
    monthly_json MEDIUMTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
);