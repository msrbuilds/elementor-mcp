"""Build the retained local demo only through real EMCP MCP calls."""
import importlib.util
import json
import pathlib
import uuid

spec = importlib.util.spec_from_file_location('flatsome_mcp', pathlib.Path(__file__).with_name('flatsome-mcp.py'))
mcp = importlib.util.module_from_spec(spec)
spec.loader.exec_module(mcp)
STATE = mcp.EVIDENCE / 'landing-state.json'
state = json.loads(STATE.read_text()) if STATE.exists() else {'run': str(uuid.uuid4()), 'steps': {}}

def step(name, fn):
    if name not in state['steps']:
        state['steps'][name] = fn()
        STATE.write_text(json.dumps(state, indent=2), encoding='utf-8')
        print('PASS MCP', name, flush=True)
    return state['steps'][name]

def write(name, args):
    operation = str(uuid.uuid5(uuid.UUID(state['run']), name + json.dumps(args, sort_keys=True)))
    return mcp.call(name, {'operation_id': operation, **args})

def theme_write(op, args):
    operation = str(uuid.uuid5(uuid.UUID(state['run']), op + json.dumps(args, sort_keys=True)))
    return mcp.theme(op, {'operation_id': operation, **args}, True)

INK = '#242B29'
MUTED = '#505A55'
CREAM = '#F5F2EB'
RUST = '#9B4329'
LINE = '#C8C9BE'

def b(name, attrs=None, children=None):
    return {'blockName': name if '/' in name else 'flatsome/' + name, 'attrs': attrs or {}, 'innerBlocks': children or []}

def text(content, **attrs):
    return b('text', {'content': '<p>' + content + '</p>', 'fontSize': '17px', 'lineHeight': 1.65, 'color': MUTED, **attrs})

def heading(content, level=2, **attrs):
    return b('heading', {'content': content, 'level': level, 'color': INK, 'fontFamily': 'Georgia, serif', 'fontWeight': '400',
                         'fontSize': {'lg': '46px' if level == 2 else '28px', 'md': '38px' if level == 2 else '26px', 'sm': '32px' if level == 2 else '24px'},
                         'lineHeight': 1.12, **attrs})

def eyebrow(content, color=RUST):
    return text(content, fontSize='12px', fontWeight='700', color=color)

def col(children, width=12, **attrs):
    return b('col', {'span': {'lg': width, 'md': width if width > 4 else 6, 'sm': 12}, 'blockGap': '20px', **attrs}, children)

def row(children, **attrs):
    return b('row', {'maxWidth': '1200px', 'marginInline': 'auto', 'gap': {'lg': '40px', 'md': '32px', 'sm': '28px'}, **attrs}, children)

def section(children, **attrs):
    return b('section', {'paddingBlock': {'lg': '88px', 'md': '64px', 'sm': '44px'}, 'paddingInline': {'lg': '48px', 'md': '32px', 'sm': '22px'},
                         'background': CREAM, 'color': INK, 'fontFamily': 'Arial, sans-serif', **attrs}, children)

def button(label, url, outline=False):
    return b('button', {'text': label, 'url': url, 'variant': 'outline' if outline else 'fill', 'color': INK if outline else '#FFFFFF',
                        'background': INK if outline else RUST, 'borderRadius': '0', 'paddingBlock': '14px', 'paddingInline': '22px', 'fontSize': '15px', 'fontWeight': '600'})

def photo(identity, alt, **attrs):
    return b('image', {'id': identity, 'alt': alt, 'size': 'full', 'aspectRatio': '4/3', 'width': '100%', **attrs})

def content_tree():
    hero = section([
        row([col([eyebrow('NORTHLINE STUDIO / CONSIDERED INTERIORS'),
                  heading('Room to live.<br>Space to breathe.', 1, fontSize={'lg': '76px', 'md': '58px', 'sm': '44px'}, letterSpacing='-0.035em'),
                  text('Warm materials. Natural light. A place that feels like you. Thoughtful interior design begins with how you want to live.'),
                  b('flex', {'gap': '14px', 'flexWrap': 'wrap'}, [button('Explore our approach', '#approach'), button('View the moodboard', '#work', True)]),
                  text('A quieter point of view, for everyday living.', fontSize='13px')], 6, span={'lg': 6, 'md': 12, 'sm': 12}),
             col([photo(1875, 'Sunlit living room with ochre lounge chairs, a round wood table and forest views.', aspectRatio={'lg': '4/5', 'md': '16/9', 'sm': '4/3'}),
                  text('01 / Warmth, light and room to pause.', fontSize='12px')], 6, span={'lg': 6, 'md': 12, 'sm': 12})], alignItems='center')
    ], className='northline-hero', paddingTop={'lg': '56px', 'md': '44px', 'sm': '32px'})
    approach = section([row([
        col([eyebrow('THE NORTHLINE APPROACH'), heading('Good spaces start<br>with listening.', anchor='approach')], 5),
        col([text('A home should support the small rituals that make a day feel good. We begin with your routines, the things you love and the space you already have.'),
             text('From the first layout to the last finishing detail, the aim is simple: less friction, more comfort, and materials that feel right together.')], 7)
    ])], background='#E6E8DF', className='northline-approach')
    services = section([row([col([eyebrow('HOW WE CAN HELP'), heading('A considered plan.<br>At every scale.', anchor='services')])]),
                        row([col([eyebrow('01 / DIRECTION'), heading('A fresh perspective', 3), text('A focused design consultation to untangle the brief, review your layout and give the next decisions a clear direction.')], 4, borderTopWidth='1px', borderColor=LINE, paddingTop='24px'),
                             col([eyebrow('02 / ROOM DESIGN'), heading('One room, resolved', 3), text('A cohesive plan for furniture, color, lighting and materials. Bring the individual pieces together into a room that works.')], 4, borderTopWidth='1px', borderColor=LINE, paddingTop='24px'),
                             col([eyebrow('03 / WHOLE HOME'), heading('A connected vision', 3), text('An interior direction that carries from room to room, with a shared palette and thoughtful details that make the whole feel complete.')], 4, borderTopWidth='1px', borderColor=LINE, paddingTop='24px')], marginTop='40px')], className='northline-services')
    work = section([row([col([eyebrow('THE MOODBOARD'), heading('Natural textures.<br>Lasting character.', anchor='work'), text('A visual study in calm interiors and thoughtful architecture. Reference imagery, rather than commissioned Northline projects.')])]),
                    row([col([photo(1874, 'Soft neutral living room with curved seating, wood furniture and a glowing floor lamp.'), heading('Soft forms, quiet tones', 3), text('Interior direction / a study in texture', fontSize='13px')], 7),
                         col([photo(1873, 'Modern house exterior framed by trees.'), heading('Connected to the outdoors', 3), text('Architectural direction / light and landscape', fontSize='13px')], 5)], marginTop='40px')], background='#FFFFFF', className='northline-work')
    process = section([row([col([eyebrow('FROM FIRST THOUGHT TO FINAL DETAIL'), heading('A clear path forward.', anchor='process')])]),
                       row([col([eyebrow('01'), heading('Listen', 3), text('Define the brief around the way you live, your priorities and your budget.')], 3),
                            col([eyebrow('02'), heading('Explore', 3), text('Find a direction through layout studies, material samples and a shared moodboard.')], 3),
                            col([eyebrow('03'), heading('Refine', 3), text('Resolve the selections and details into a coordinated, practical design plan.')], 3),
                            col([eyebrow('04'), heading('Bring it home', 3), text('Use the agreed plan to guide purchasing, installation and finishing touches.')], 3)], marginTop='40px')], className='northline-process')
    faq_items = [
        ('Where does a project begin?', 'With a conversation about the space, how it is used and what you would like to change. A clear brief comes before any design decisions.'),
        ('Can existing furniture stay?', 'Yes. The pieces you love can be the starting point. A considered plan makes room for existing furniture alongside new materials and finishes.'),
        ('How is the scope decided?', 'The scope follows the brief. A single-room plan and a whole-home direction need different levels of detail, so deliverables should be agreed before work begins.'),
        ('Is this a real studio website?', 'Northline Studio is a fictional design concept created for a local demonstration. The photographs are visual references and are not presented as completed client projects.')]
    faq = section([row([col([eyebrow('A FEW USEFUL ANSWERS'), heading('Before we begin.', anchor='questions')], 5),
                        col([b('accordion', {'openMultiple': False, 'autoOpen': False, 'icon': {'type': 'plus'}, 'fontSize': '18px', 'color': INK, 'gap': '0'},
                               [b('accordion-item', {'title': title}, [text(answer)]) for title, answer in faq_items])], 7)])], background='#E6E8DF', className='northline-faq')
    return [hero, approach, services, work, process, faq]

def main():
    assert mcp.call('flatsome-get-context')['site_url'] == 'https://elementor-mcp.test'
    styles = step('styles-before', lambda: mcp.theme('get-global-styles'))
    style_change = step('style-patch', lambda: theme_write('patch-global-styles', {'expected_hash': styles['expected_hash'], 'patch': {'styles': {'color': {'text': INK}}}}))
    step('style-undo', lambda: mcp.call('rollback-change', {'id': style_change['change_id']}))
    restored = mcp.theme('get-global-styles')
    assert restored['expected_hash'] == styles['expected_hash'] and restored['exists'] == styles['exists']
    created = step('create-page', lambda: write('flatsome-create-page', {'title': 'Northline Studio | Considered interiors'}))
    identity = created['post_id']
    tree = content_tree()
    step('validate-tree', lambda: mcp.call('flatsome-validate-page', {'tree': tree}))
    page = step('set-tree', lambda: write('flatsome-set-page-tree', {'post_id': identity, 'expected_hash': created['expected_hash'], 'replace_all': True, 'tree': tree}))
    cta_tree = [section([row([col([eyebrow('MAKE ROOM FOR WHAT MATTERS', '#87391F'), heading('Your space,<br>with more intention.'), text('Start with a single room, a new perspective or a plan for the whole home.'), button('Explore design services', '#services')])])], background='#DED5C5', className='northline-cta')]
    pattern = step('create-pattern', lambda: write('flatsome-create-pattern', {'title': 'Northline Studio / Closing invitation', 'tree': cta_tree}))
    page = step('insert-pattern', lambda: write('flatsome-insert-pattern', {'post_id': identity, 'expected_hash': page['expected_hash'], 'pattern_id': pattern['post_id'], 'synced': True, 'path': '5', 'position': 'after'}))
    footer = step('create-footer', lambda: theme_write('create-template-part', {'title': 'Northline Studio Footer', 'slug': 'northline-studio-footer', 'area': 'footer',
        'tree': [section([row([col([text('NORTHLINE STUDIO', fontSize='14px', fontWeight='700', color=CREAM), text('Considered spaces. Everyday living.', fontSize='13px', color='#E5E5DB')], 5),
                               col([text('A fictional design concept. Photography shown as visual reference.', fontSize='12px', color='#E5E5DB')], 7)])], background=INK, paddingBlock='32px')]}))
    template = step('create-template', lambda: theme_write('create-template', {'title': 'Northline Studio Landing', 'slug': 'northline-studio-landing',
        'tree': [b('section', {'tagName': 'main', 'paddingBlock': '0'}, [b('core/post-content', {'align': 'full'})]),
                 b('core/template-part', {'slug': footer['document']['slug'], 'theme': 'flatsome-theme', 'tagName': 'footer'})]}))
    page = step('assign-template', lambda: theme_write('assign-template', {'post_id': identity, 'expected_hash': page['expected_hash'], 'template': template['document']['slug']}))
    page = step('set-slug', lambda: write('flatsome-update-page-settings', {'post_id': identity, 'expected_hash': page['expected_hash'], 'slug': 'northline-studio'}))
    # A real edit, stale rejection and undo through MCP, followed by original-snapshot structural operations.
    edited = step('edit-heading', lambda: write('flatsome-patch-page', {'post_id': identity, 'expected_hash': page['expected_hash'], 'operations': [{'op': 'set', 'path': '0.0.0.1', 'attrs': {'content': 'A thoughtful temporary heading.'}}]}))
    step('stale-rejected', lambda: mcp.call('flatsome-patch-page', {'post_id': identity, 'expected_hash': page['expected_hash'], 'operation_id': str(uuid.uuid4()), 'operations': [{'op': 'remove', 'path': '0'}]}, error=True))
    step('heading-undo', lambda: mcp.call('rollback-change', {'id': edited['change_id']}))
    fresh = mcp.call('flatsome-get-page', {'post_id': identity})
    duplicate = step('duplicate', lambda: write('flatsome-patch-page', {'post_id': identity, 'expected_hash': fresh['expected_hash'], 'operations': [{'op': 'duplicate', 'path': '4'}]}))
    moved = step('move', lambda: write('flatsome-patch-page', {'post_id': identity, 'expected_hash': duplicate['expected_hash'], 'operations': [{'op': 'move', 'path': '5', 'to': '0', 'position': 'before'}]}))
    removed = step('remove', lambda: write('flatsome-patch-page', {'post_id': identity, 'expected_hash': moved['expected_hash'], 'operations': [{'op': 'remove', 'path': '0'}]}))
    step('publish', lambda: write('flatsome-publish-page', {'post_id': identity, 'expected_hash': removed['expected_hash']}))
    final = mcp.call('flatsome-get-page', {'post_id': identity})
    (mcp.EVIDENCE / 'landing-final.json').write_text(json.dumps(final, indent=2), encoding='utf-8')
    print(json.dumps({'post_id': identity, 'url': final['url'], 'editor_url': final['editor_url'], 'pattern': pattern['post_id'], 'template': template['post_id'], 'footer': footer['post_id']}), flush=True)

if __name__ == '__main__':
    main()
