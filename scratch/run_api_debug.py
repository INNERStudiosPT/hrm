import paramiko
import sys

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('51.68.161.13', port=2222, username='fivem', password='owBzQOsxDuGo')

php_code = """<?php
require_once '/var/www/html/src/vendor/autoload.php';
use OrangeHRM\\Framework\\Framework;
use OrangeHRM\\Config\\Config;
use OrangeHRM\\Framework\\Http\\Request;
use OrangeHRM\\Attendance\\Api\\EmployeeAttendanceRecordAPI;
use OrangeHRM\\Core\\Api\\V2\\Request as ApiRequest;

new Framework('prod', false);
$request = new Request();
$pluginConfigs = Config::get('ohrm_plugin_configs');
foreach (array_values($pluginConfigs) as $pluginConfig) {
    require_once $pluginConfig['filepath'];
    $configClass = new $pluginConfig['classname']();
    $configClass->initialize($request);
}

$dates = [
    "2026-05-06", "2026-05-07", "2026-05-08", "2026-05-11",
    "2026-05-12", "2026-05-13", "2026-05-14", "2026-05-15",
    "2026-05-18", "2026-05-19", "2026-05-20", "2026-05-21",
    "2026-05-22", "2026-05-25", "2026-05-26", "2026-05-27"
];

foreach ($dates as $date) {
    $frameworkRequest = new Request(
        ['date' => $date], // query
        [], // request
        ['empNumber' => 2] // attributes
    );
    $apiRequest = new ApiRequest($frameworkRequest);
    $api = new EmployeeAttendanceRecordAPI($apiRequest);
    $result = $api->getAll();
    $data = $result->normalize();
    echo "Date $date: " . count($data) . " records.\\n";
}
"""

sftp = ssh.open_sftp()
with sftp.file("/home/fivem/apps/hrm/test_api_loop.php", "w") as f:
    f.write(php_code)
sftp.close()

# Copy into container
ssh.exec_command("docker cp /home/fivem/apps/hrm/test_api_loop.php hrm-web:/var/www/html/test_api_loop.php")

# Execute
stdin, stdout, stderr = ssh.exec_command("docker exec hrm-web php /var/www/html/test_api_loop.php")
out = stdout.read().decode('utf-8', errors='replace')
err = stderr.read().decode('utf-8', errors='replace')

print("=== API LOOP STDOUT ===")
print(out)
print("=== API LOOP STDERR ===")
print(err)

# Cleanup
ssh.exec_command("docker exec hrm-web rm -f /var/www/html/test_api_loop.php")
ssh.close()
