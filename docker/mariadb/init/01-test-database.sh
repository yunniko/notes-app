#!/bin/sh
# Runs once, when the database volume is first created: adds an empty database for the
# backend's integration tests so they never touch the app's own data.
mariadb --protocol=socket -uroot -p"$MARIADB_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS notes_test CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;
GRANT ALL PRIVILEGES ON notes_test.* TO '$MARIADB_USER'@'%';
SQL
