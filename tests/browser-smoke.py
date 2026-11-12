"""Run production browser smoke tests against local mock APIs; no WordPress data changes."""
import functools
import http.server
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
import threading

root = Path(__file__).resolve().parent.parent
chrome = os.environ.get('PX_CHROME') or shutil.which('chromium') or shutil.which('google-chrome')
if not chrome:
    chrome = r'C:\Program Files\Google\Chrome\Application\chrome.exe'
if not Path(chrome).exists():
    raise SystemExit('Set PX_CHROME to the Chrome/Chromium executable.')

class QuietHandler(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *args):
        pass

handler = functools.partial(QuietHandler, directory=str(root))
server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), handler)
threading.Thread(target=server.serve_forever, daemon=True).start()
try:
    with tempfile.TemporaryDirectory(prefix='px-appointment-smoke-', ignore_cleanup_errors=True) as profile:
        for fixture in ['admin-cache-smoke.html?entry=wordpress', 'admin-cache-smoke.html', 'ui-smoke.html', 'ui-smoke.html?scenario=preset', 'ui-smoke.html?scenario=failure', 'admin-smoke.html', 'service-modal-smoke.html', 'service-modal-smoke.html?scenario=coexist', 'service-modal-smoke.html?scenario=failure']:
            url = f'http://127.0.0.1:{server.server_port}/tests/{fixture}'
            result = subprocess.run([
                chrome, '--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
                f'--user-data-dir={profile}', '--dump-dom', '--timeout=15000',
                '--virtual-time-budget=15000', url,
            ], capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=30)
            match = re.search(r'<pre id="test-result"[^>]*>([^<]+)', result.stdout)
            message = match.group(1) if match else 'FAIL no browser test result'
            print(message)
            if not message.startswith('PASS'):
                raise SystemExit(1)
finally:
    server.shutdown()
    server.server_close()
