"""Real compact MCP authoring harness. State/transcripts stay in ignored evidence storage."""
import importlib.util
import json
import pathlib
import sys
import uuid
import os

ROOT = pathlib.Path(__file__).resolve().parents[2]
EVIDENCE = pathlib.Path(os.environ.get('EMCP_FLATSOME_EVIDENCE', str(ROOT / 'docs/flatsome-e2e-20261104'))).resolve()
spec = importlib.util.spec_from_file_location('local_mcp', pathlib.Path(__file__).with_name('oxygen-mcp-client.py'))
client = importlib.util.module_from_spec(spec)
spec.loader.exec_module(client)
EVIDENCE.mkdir(parents=True, exist_ok=True)

def call(name, args=None, error=False):
    args = args or {}
    result = client.rpc_tool('emcp-tools-call-tool', {'name': 'emcp-tools/' + name, 'arguments': args})
    data = result.get('structuredContent')
    if data is None:
        raw = next(c['text'] for c in result['content'] if c['type'] == 'text')
        try:
            data = json.loads(raw)
        except json.JSONDecodeError:
            data = {'message': raw}
    with (EVIDENCE / 'mcp-transcript.jsonl').open('a', encoding='utf-8') as stream:
        safe_args = {k: ('[base64 omitted]' if k == 'data' else v) for k, v in args.items()}
        stream.write(json.dumps({'tool': name, 'arguments': safe_args, 'result': data, 'isError': result.get('isError', False)}) + '\n')
    if result.get('isError') and not error:
        raise RuntimeError(json.dumps(data))
    return data

def mutate(name, args):
    return call(name, {'operation_id': str(uuid.uuid4()), **args})

def theme(op, args=None, write=False):
    params = dict(args or {})
    if write:
        params.setdefault('operation_id', str(uuid.uuid4()))
    return call('flatsome-theme-' + ('write' if write else 'read'), {'operation': op, 'arguments': params})

def discover():
    context = call('flatsome-get-context')
    assert context['site_url'] == 'https://elementor-mcp.test'
    tools = client.rpc_tool('list')
    (EVIDENCE / 'mcp-tools-list.json').write_text(json.dumps(tools, indent=2), encoding='utf-8')
    names = ['flatsome-' + n for n in ['create-page','set-page-tree','patch-page','create-pattern','insert-pattern','publish-page','theme-read','theme-write']]
    names += ['list-media','sideload-image','upload-media','update-media','rollback-change']
    schemas = client.rpc_tool('emcp-tools-get-tool-schema', {'names': ['emcp-tools/' + n for n in names]})
    (EVIDENCE / 'tool-schemas.json').write_text(json.dumps(schemas, indent=2), encoding='utf-8')
    catalog = call('flatsome-list-blocks')
    (EVIDENCE / 'coverage.json').write_text(json.dumps(catalog, indent=2), encoding='utf-8')
    for name in ['section','row','column','grid','group','heading','text','button','image','accordion','accordion-item','icon','list','slider','slide','tabs','tab','products']:
        schema = call('flatsome-get-block-schema', {'name': 'flatsome/' + name})
        (EVIDENCE / ('schema-' + name + '.json')).write_text(json.dumps(schema, indent=2), encoding='utf-8')
        print('schema', name, schema['registered'], schema['writable'], flush=True)
    supports = call('flatsome-get-block-supports')
    (EVIDENCE / 'supports.json').write_text(json.dumps(supports, indent=2), encoding='utf-8')
    for name in supports['names']:
        data = call('flatsome-get-block-supports', {'name': name})
        (EVIDENCE / ('support-' + name.replace('/', '-') + '.json')).write_text(json.dumps(data, indent=2), encoding='utf-8')
    media = call('list-media', {'per_page': 30})
    (EVIDENCE / 'media.json').write_text(json.dumps(media, indent=2), encoding='utf-8')
    print('MCP discovery complete', flush=True)

if __name__ == '__main__':
    if sys.argv[1] == 'discover':
        discover()
    elif sys.argv[1] == 'call':
        print(json.dumps(call(sys.argv[2], json.loads(sys.argv[3]) if len(sys.argv) > 3 else {}), indent=2))
    elif sys.argv[1] == 'schema':
        for name in sys.argv[2:]:
            data = call('flatsome-get-block-schema', {'name': name if '/' in name else 'flatsome/' + name})
            (EVIDENCE / ('schema-' + name.replace('/', '-') + '.json')).write_text(json.dumps(data, indent=2), encoding='utf-8')
            print('Discovered', name, flush=True)
