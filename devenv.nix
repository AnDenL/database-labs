{ pkgs, ... }: {
  services.mysql = {
    enable = true;
    package = pkgs.mariadb;
    initialDatabases = [{ name = "farm_db"; }];
  };

  languages.php = {
    enable = true;
  };

  enterShell = ''
    if ! mysqladmin ping --socket="$MYSQL_UNIX_PORT" --silent 2>/dev/null; then
      echo "Starting MariaDB service..."
      mysqld_safe --datadir="$DEVENV_STATE/mysql/data" --socket="$MYSQL_UNIX_PORT" > /dev/null 2>&1 &
      while ! mysqladmin ping --socket="$MYSQL_UNIX_PORT" --silent 2>/dev/null; do
        sleep 0.2
      done
      echo "MariaDB is ready!"
    fi
  '';
}
