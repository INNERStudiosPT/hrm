import paramiko

def main():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect('51.68.161.13', port=2222, username='fivem', password='owBzQOsxDuGo')

    cmd = 'docker compose exec -T hrm-web cat /var/www/html/lib/confs/Conf.php'
    stdin, stdout, stderr = ssh.exec_command(f"cd /home/fivem/apps/hrm && {cmd}")
    print("=== Conf.php ===")
    print(stdout.read().decode('utf-8'))

    ssh.close()

if __name__ == '__main__':
    main()
