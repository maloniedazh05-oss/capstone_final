REQUIREMENTS(Once download and installed - Works Completely offline/local): Windows >= 10

- Python >= 3.11 version: https://www.python.org/downloads/windows/
3.11.9: https://www.python.org/ftp/python/3.11.9/python-3.11.9-amd64.exe

- Node JS:
https://nodejs.org/en/download
#Direct dl: https://nodejs.org/dist/v24.21.0/node-v24.21.0-x64.msi

- Xampp: https://www.apachefriends.org/
#Run as admin when opening the program.


=======================================
------------- DATABASE ----------------
=======================================
-- Latest DB:

CREATE DATABASE IF NOT EXISTS rural_urban;
USE rural_urban;

CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(30) UNIQUE,
    user VARCHAR(50) UNIQUE NOT NULL,
    pass VARCHAR(255),
    admin BOOLEAN UNIQUE DEFAULT NULL,
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active'
);

CREATE TABLE inventory (
    prod_id VARCHAR(15) PRIMARY KEY,
    product VARCHAR(30) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    unit VARCHAR(10) NOT NULL,
    status VARCHAR(50) DEFAULT 'Recent',
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
    status VARCHAR(30) DEFAULT 'Recent',
    receiver VARCHAR(50)
);