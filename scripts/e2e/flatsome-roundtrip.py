"""Verify the intentional native UI edit and restore the final label through MCP."""
import importlib.util
import json
import pathlib

spec = importlib.util.spec_from_file_location('flatsome_mcp', pathlib.Path(__file__).with_name('flatsome-mcp.py'))
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
manifest = json.loads((m.EVIDENCE / 'landing-final.json').read_text(encoding='utf8'))
page = m.call('flatsome-get-page', {'post_id': manifest['post_id']})
assert page['tree'][4]['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0]['attrs']['content'] == '<p>01 / LISTEN</p>'
assert page['tree'][0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['fontSize'] == {'lg': '76px', 'md': '58px', 'sm': '44px'}
assert page['tree'][0]['innerBlocks'][0]['innerBlocks'][1]['innerBlocks'][0]['attrs']['aspectRatio'] == {'lg': '4/5', 'md': '16/9', 'sm': '4/3'}
assert page['tree'][-1]['blockName'] == 'core/block'
(m.EVIDENCE / 'native-roundtrip-read.json').write_text(json.dumps(page, indent=2), encoding='utf8')
m.mutate('flatsome-patch-page', {'post_id': page['post_id'], 'expected_hash': page['expected_hash'],
    'operations': [{'op': 'set', 'path': '4.1.0.0', 'attrs': {'content': '<p>01</p>'}}]})
page = m.call('flatsome-get-page', {'post_id': page['post_id']})
(m.EVIDENCE / 'landing-final.json').write_text(json.dumps(page, indent=2), encoding='utf8')
print('Native save verified over MCP; responsive typography, art direction and synced pattern preserved; final label restored.')
