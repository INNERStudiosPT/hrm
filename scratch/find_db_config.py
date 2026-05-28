import paramiko

def main():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect('51.68.161.13', port=2222, username='fivem', password='owBzQOsxDuGo')

    print("=== SEARCHING CONFIG FILES ===")
    cmd = 'find /home/fivem/apps/hrm -name "*database*" -o -name "*Conf.php*" -o -name "*crypt*" -o -name "db.php" -o -name "*.yml"'
    stdin, stdout, stderr = ssh.exec_command(cmd)
    print(stdout.read().decode('utf-8'))

    # Let's check typical orangehrm db config location: /var/www/html/config/db.php or similar
    # Or let's inspect the files in the config folder inside the container
    print("=== CONTAINER CONFIG FOLDER ===")
    cmd = 'docker compose exec -T hrm-web ls -la /var/www/html/config/ /var/www/html/src/config/ /var/www/html/lib/confs/'
    stdin, stdout, stderr = ssh.exec_command(f"cd /home/fivem/apps/hrm && {cmd}")
    print(stdout.read().decode('utf-8'))
    print(stderr.read().decode('utf-8'))

    ssh.close()

if __name__ == '__main__':
    main()
