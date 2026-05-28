import paramiko

def main():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect('51.68.161.13', port=2222, username='fivem', password='owBzQOsxDuGo')

    print("=== ONBOARDING RECORDS ===")
    cmd = 'docker exec -i hrm-db mariadb -uroot -p26G0PVk5my6A1fuEzAoKQeA6lFzQc38dPTZ8 -e "SELECT * FROM orangehrm.ohrm_innerstudios_candidate_onboarding;"'
    stdin, stdout, stderr = ssh.exec_command(cmd)
    print(stdout.read().decode('utf-8'))

    ssh.close()

if __name__ == '__main__':
    main()
