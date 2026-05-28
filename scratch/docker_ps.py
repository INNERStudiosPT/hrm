import paramiko

def main():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect('51.68.161.13', port=2222, username='fivem', password='owBzQOsxDuGo')

    print("=== docker ps -a ===")
    stdin, stdout, stderr = ssh.exec_command('docker ps -a')
    print(stdout.read().decode('utf-8'))

    ssh.close()

if __name__ == '__main__':
    main()
