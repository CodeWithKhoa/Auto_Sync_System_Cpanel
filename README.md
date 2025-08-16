# 🚀 cPanel Deployment Automator

![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue)
![License](https://img.shields.io/badge/License-MIT-green)
![cPanel](https://img.shields.io/badge/cPanel-Automation-orange)

**cPanel Deployment Automator** is a PHP-based command-line interface (CLI) tool designed to **automate web application deployments** to cPanel hosting environments.  
It removes tedious manual steps, helping you achieve **efficiency, speed, and consistency** with every deployment.

---

## 📑 Table of Contents
- [✨ Key Features](#-key-features)
- [🛠️ Getting Started](#%EF%B8%8F-getting-started)
  - [1. Configuration](#1-configuration)
  - [2. Execution](#2-execution)
- [⚠️ Important Security Notes](#-important-security-notes)
- [👀 Example CLI Menu](#-example-cli-menu)
- [🤝 Contributing](#-contributing)
- [👤 Author & 📜 License](#-author--license)

---

## ✨ Key Features
This tool is more than just a simple script; it addresses real-world deployment challenges:

- **Streamlined Deployment**  
  One command handles cleaning the target directory, uploading a compressed file, unzipping, and configuring the environment.

- **Smart Database Management**  
  - Auto-creates database and user  
  - Grants privileges  
  - Drops existing tables and imports `.sql` by simulating phpMyAdmin interactions

- **Seamless Integration**  
  Automatically updates `.env` on the server with fresh DB credentials so your app runs immediately after deployment.

---

## 🛠️ Getting Started

### 1. Configuration
Edit the variables at the top of `all.php`:

| Variable            | Description                                  | Example                                |
|---------------------|----------------------------------------------|----------------------------------------|
| `$cpanel_host`      | Your cPanel host and port                    | `'yourdomain.com:2083'`                |
| `$cpanel_user`      | Your cPanel username                         | `'mycpaneluser'`                       |
| `$cpanel_pass`      | Your cPanel password                         | `'P@ssw0rdSecr3t'`                     |
| `targetDirectory`   | Path on the server where code will be placed | `'/home/user/public_html'`             |
| `localFileToUpload` | Path to your local `.zip` source code file   | `'C:/projects/my-app/dist/source.zip'` |
| `fileToEdit`        | The environment file to update               | `'.env'`                               |
| `database_name`     | Database name to create                      | `'mycpaneluser_proddb'`                |
| `db_user`           | Database user                                | `'mycpaneluser_produser'`              |
| `db_pass`           | Strong DB password                           | `'Str0ngDBP@ssw0rd!2025'`              |
| `localSqlFile`      | Path to local `.sql` file                    | `'C:/projects/my-app/backup/latest.sql'` |

---

### 2. Execution
In your **Terminal/Command Prompt**, run:

```bash
php all.php
```
The script will display an **interactive menu**.

## 👀 Example CLI Menu
### Screenshot

### Text Version
``` text
============= MENU =============
1. Chỉ Upload & Cấu hình source code
2. Chỉ Tạo Database & User
3. Chỉ Reset & Import Database
4. 🚀 TRIỂN KHAI ĐẦY ĐỦ (1 + 2 + 3)
0. Thoát
================================
Vui lòng chọn chức năng: 
```

💡 CLI menu supports **Vietnamese** (default).
You can easily customize text to **English** in `all.php`.

## ⚠️ Important Security Notes 

**⚠️ Warning**

* The `all.php` file contains sensitive credentials.
**Never upload or commit this file** to public repositories (e.g., GitHub).

* This script executes permanent actions such as deleting files and dropping database tables.
Always back up your project and database before running it.

##  🤝 Contributing

Pull requests are welcome!
For major changes, please open an issue first to discuss what you’d like to modify.

## 👤 Author & 📜 License

**Author**: Trần Đăng Khoa & Gemini

**License**: [MIT License](./LICENSE) – free to use, modify, and distribute.
