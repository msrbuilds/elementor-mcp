/**
 * Shared Connection screen fixture for the Jest tests.
 */
export const data = {
	endpoint: 'https://ex.test/wp-json/mcp/emcp-tools-server',
	siteUrl: 'https://ex.test',
	clients: [
		{
			id: 'claude-desktop',
			label: 'Claude Desktop',
			image: '',
			methods: {
				bundle: true,
				cli: null,
				ai_prompt: true,
				json: [ 'npx', 'http' ],
			},
			oauth: { type: 'connector', app: 'Claude Desktop' },
			guide: '',
			guideTitle: '',
			cli: true,
		},
		{
			id: 'claude-ai',
			label: 'Claude.ai',
			image: '',
			methods: {
				bundle: false,
				cli: null,
				ai_prompt: true,
				json: [ 'remote' ],
			},
			oauth: {
				type: 'connector',
				app: 'claude.ai',
				deeplink: 'claude-ai',
			},
			guide: '',
			guideTitle: '',
			cli: false,
		},
	],
	localCli: { available: true, command: 'wp', path: 'F:/sites/ex' },
	users: [ { id: 1, login: 'admin', name: 'Admin' } ],
	currentUserId: 1,
	oauth: { available: true, enabled: true },
	apps: [
		{
			id: 'cl_1',
			name: 'Old app',
			state: 'connected',
			activeTokens: 1,
			user: 'admin',
			created: 1,
			gateway: false,
		},
	],
	mcpb: {
		url: '/wp-admin/admin-post.php',
		action: 'emcp_tools_download_mcpb',
		nonce: 'n',
	},
};
