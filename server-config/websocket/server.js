const WebSocket = require('ws');
const { spawn } = require('child_process');

const PORT = 8765;
const wss = new WebSocket.Server({ port: PORT });

console.log('WebSocket log server started on port ' + PORT);

const LOGS = {
    fail2ban: '/var/log/fail2ban.log',
    phpfpm:   '/var/log/php8.3-fpm.log',
    system:   '/var/log/syslog',
    backup:   '/mnt/datos/backups/backup.log',
};

wss.on('connection', function(ws, req) {
    console.log('Client connected from: ' + (req.socket.remoteAddress || 'unknown'));

    let activeLog  = 'fail2ban';
    let process_   = null;

    function startTail(logKey) {
        if (process_) process_.kill();
        const path = LOGS[logKey] || LOGS['fail2ban'];
        process_ = spawn('tail', ['-f', '-n', '50', path]);

        process_.stdout.on('data', function(data) {
            if (ws.readyState === WebSocket.OPEN) {
                ws.send(data.toString());
            }
        });

        process_.stderr.on('data', function(data) {
            if (ws.readyState === WebSocket.OPEN) {
                ws.send('[ERROR] ' + data.toString());
            }
        });
    }

    startTail(activeLog);

    ws.on('message', function(message) {
        const requestedLog = message.toString().trim();
        if (LOGS[requestedLog]) {
            activeLog = requestedLog;
            startTail(activeLog);
        }
    });

    ws.on('close', function() {
        console.log('Client disconnected');
        if (process_) process_.kill();
    });

    ws.on('error', function(err) {
        console.log('WebSocket error: ' + err.message);
        if (process_) process_.kill();
    });
});
