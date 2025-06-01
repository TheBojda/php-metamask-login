# PHP MetaMask Login Demo

## Usage

To use this system, you need a runtime environment accessible via **HTTPS** (such as **nginx**, **Apache**, etc.), since MetaMask only supports secure (HTTPS) links.

## Setup

1. Create a `.env` file.
2. Set the `SITE_HOST` variable to the domain where the scripts will run.
3. Set the web root directory to the `public` folder.

## Login Flow

- Open `index.php`, which displays a QR code.
- Scan the QR code with your phone.
- The **deeplink** will open **MetaMask** and load the `login.php` page with the generated `sessionid`.

## Authentication

- After a successful signature, `login.php` will store the Ethereum address in the database.
- `index.php` refreshes itself every 10 seconds to check whether the Ethereum address has been recorded for the current `sessionid`.
- If the address is found, the login is considered successful.
