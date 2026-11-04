"""Two independent native WordPress processes prove save coordination, not stub locks."""
import json
import pathlib
import subprocess
import queue
import threading
import os

ROOT = pathlib.Path(__file__).resolve().parents[2]
PHP = 'F:/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe'
BASE = [PHP, 'C:/wp-cli/wp-cli.phar', '--path=' + os.environ.get('EMCP_FLATSOME_WP_PATH', 'F:/laragon/www/elementor-mcp'), '--user=1',
        'eval-file', str(ROOT / 'pro/tests/smoke/flatsome-concurrency.php')]

def run(mode, *args):
    result = subprocess.run(BASE + [mode, *map(str, args)], text=True, capture_output=True, timeout=90)
    if result.returncode:
        raise RuntimeError(result.stdout + result.stderr)
    return next((json.loads(line) for line in result.stdout.splitlines() if line.startswith('{')), {})

def holder(mode, identity):
    process = subprocess.Popen(BASE + [mode, str(identity)], stdin=subprocess.PIPE, stdout=subprocess.PIPE,
                               stderr=subprocess.PIPE, text=True)
    ready = queue.Queue()
    def read():
        for line in process.stdout:
            if line.strip() == 'READY':
                ready.put(True)
                return
    threading.Thread(target=read, daemon=True).start()
    ready.get(timeout=90)
    return process

if __name__ == '__main__':
    fixture = run('setup')
    held = None
    change = None
    global_fixture = None
    try:
        held = holder('hold-emcp', fixture['id'])
        native = run('native', fixture['id'])
        assert native['status'] == 409, native
        assert run('comment', fixture['id'])['error'] == 'flatsome_busy'
        assert run('emcp', fixture['id'], fixture['hash'])['error'] == 'flatsome_busy'
        held.communicate('\n', timeout=30); held = None
        change = run('change', fixture['id'])
        held = holder('hold-native', fixture['id'])
        assert run('emcp', fixture['id'], fixture['hash'])['error'] == 'flatsome_busy'
        assert run('undo', fixture['id'], change['change_id'])['error'] == 'flatsome_busy'
        held.communicate('\n', timeout=30); held = None
        assert run('emcp', fixture['id'], fixture['hash'])['error'] == 'stale_content'
        assert 'error' in run('undo', fixture['id'], change['change_id'])
        assert run('read', fixture['id'])['content'] == 'Native winner.'
        held = holder('prime-global', 0)
        global_fixture = run('create-global')['id']
        stdout, stderr = held.communicate('\n', timeout=30)
        assert held.returncode == 0, stdout + stderr
        held = None
        answer = next(json.loads(line) for line in stdout.splitlines() if line.startswith('{'))
        assert answer['error'] == 'stale_content'
        print('PASS: native/MCP/comment/undo contention, stale losers, and cached first-global-style identity race (10 assertions)')
    finally:
        if held:
            held.communicate('\n', timeout=30)
        if global_fixture:
            run('cleanup-global', global_fixture)
        run('cleanup', fixture['id'])
        if change:
            run('receipt-cleanup', fixture['id'], change['change_id'], change['operation_id'])
