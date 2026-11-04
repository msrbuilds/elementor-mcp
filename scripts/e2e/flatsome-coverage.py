"""MCP-only disposable core-family and real WooCommerce display fixture."""
import importlib.util
import json
import pathlib
import sys

spec = importlib.util.spec_from_file_location('flatsome_mcp', pathlib.Path(__file__).with_name('flatsome-mcp.py'))
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
statefile = m.EVIDENCE / 'coverage-fixture.json'

def b(name, attrs=None, children=None):
    return {'blockName': name if '/' in name else 'flatsome/' + name, 'attrs': attrs or {}, 'innerBlocks': children or []}

def text(value):
    return b('text', {'content': '<p>' + value + '</p>'})

def tree():
    return [b('section', {'paddingBlock': '48px', 'paddingInline': '24px', 'background': '#FFFFFF', 'color': '#242B29'}, [
        b('heading', {'level': 1, 'content': 'Flatsome qualification fixture'}),
        b('flex', {'flexDirection': 'column', 'gap': '24px'}, [
            b('card', {}, [b('card-content', {}, [b('heading', {'level': 2, 'content': 'Native card'}), text('Card body retained.')])]),
            b('icon-box', {'icon': {'collection': 'basic', 'id': 'check', 'size': '32px'}}, [text('Icon box content.')])]),
        b('icon', {'collection': 'basic', 'id': 'heart', 'size': '32px'}),
        b('banner', {'height': '180px', 'background': '#E6E8DF'}, [b('heading', {'level': 2, 'content': 'Native banner'})]),
        b('banner-grid', {'height': '180px', 'gap': '16px'}, [
            b('banner', {'columns': {'lg': 6, 'sm': 12}, 'background': '#E6E8DF'}, [text('Banner grid first cell.')]),
            b('banner', {'columns': {'lg': 6, 'sm': 12}, 'background': '#DED5C5'}, [text('Banner grid second cell.')])]),
        b('testimonial', {'author': 'Fictional fixture author', 'source': 'Test data'}, [text('A demonstration quote, not a customer endorsement.')]),
        b('list', {}, [b('list-item', {'text': 'First native list item', 'enabled': True}), b('list-item', {'text': 'Second native list item', 'enabled': False})]),
        b('tabs', {}, [b('tab-list'), b('tab-panels', {}, [
            b('tab-panel', {'title': 'Material'}, [text('Material panel content.')]),
            b('tab-panel', {'title': 'Light'}, [text('Light panel content.')])])]),
        b('slider', {'autoplay': 0, 'loop': False}, [b('slider-viewport', {}, [
            b('section', {'height': '160px', 'background': '#E6E8DF'}, [text('First slide content.')]),
            b('section', {'height': '160px', 'background': '#DED5C5'}, [text('Second slide content.')])]),
            b('slider-button', {'direction': 'prev'}), b('slider-button', {'direction': 'next'}), b('slider-dots')]),
        b('heading', {'level': 2, 'content': 'Existing store products'}),
        b('products', {'query': {'ids': [2117, 2118, 2119], 'perPage': 3}, 'columns': {'lg': 3, 'sm': 1}}),
    ])]

def save(state):
    statefile.write_text(json.dumps(state, indent=2), encoding='utf8')

if __name__ == '__main__':
    assert m.call('flatsome-get-context')['site_url'] == 'https://elementor-mcp.test'
    if sys.argv[1] == 'create':
        if statefile.exists() and not json.loads(statefile.read_text())['cleaned']:
            raise RuntimeError('An owned fixture already exists; inspect or clean it first.')
        blocks = tree()
        valid = m.call('flatsome-validate-page', {'tree': blocks})
        state = {'cleaned': False, 'changes': [], 'tree': blocks}
        def write(tool, args, theme=False):
            result = m.theme(tool, args, True) if theme else m.mutate('flatsome-' + tool, args)
            state['changes'].append(result['change_id'])
            state['page'] = result['document']
            save(state)
            return result
        page = write('create-page', {'title': 'Owned Flatsome qualification fixture'})
        page = write('set-page-tree', {'post_id': page['post_id'], 'expected_hash': page['expected_hash'], 'replace_all': True, 'tree': blocks})
        page = write('assign-template', {'post_id': page['post_id'], 'expected_hash': page['expected_hash'], 'template': 'northline-studio-landing'}, True)
        page = write('publish-page', {'post_id': page['post_id'], 'expected_hash': page['expected_hash']})
        print(json.dumps({'post_id': page['post_id'], 'url': page['document']['url']}))
    elif sys.argv[1] == 'cleanup':
        state = json.loads(statefile.read_text())
        if not state['cleaned']:
            for index in reversed(range(len(state['changes']))):
                # Only this disposable fixture's updates may bypass a changed
                # shared template hash. Final creation undo retains all guards.
                m.call('rollback-change', {'id': state['changes'][index], 'force': index > 0})
            state['cleaned'] = True
            save(state)
        print('Owned fixture removed through MCP History undo.')
