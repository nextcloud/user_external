export type FieldType = 'text' | 'number' | 'password' | 'checkbox' | 'select'

export interface FieldSpec {
	key: string
	label: string
	type: FieldType
	required?: boolean
	default?: string | number | boolean
	options?: string[] // for select fields
	optionLabels?: string[] // display labels for options, parallel to options[]
}

export interface BackendSpec {
	label: string
	fields: FieldSpec[]
	/** Maps sslmode value → its canonical default port, used for auto-selection. */
	sslPortMap?: Record<string, number>
}

export type BackendClass =
	| '\\OCA\\UserExternal\\IMAP'
	| '\\OCA\\UserExternal\\FTP'
	| '\\OCA\\UserExternal\\SMB'
	| '\\OCA\\UserExternal\\SSH'
	| '\\OCA\\UserExternal\\BasicAuth'
	| '\\OCA\\UserExternal\\WebDavAuth'
	| '\\OCA\\UserExternal\\XMPP'

export const BACKEND_SPECS: Record<BackendClass, BackendSpec> = {
	'\\OCA\\UserExternal\\IMAP': {
		label: 'IMAP',
		fields: [
			{ key: 'host', label: 'IMAP server', type: 'text', required: true },
			{ key: 'port', label: 'Port', type: 'number', default: 143 },
			{ key: 'sslmode', label: 'SSL mode', type: 'select', options: ['', 'ssl', 'tls'], optionLabels: ['(None)', 'SSL/TLS', 'STARTTLS'], default: '' },
			{ key: 'domain', label: 'Email domain restriction', type: 'text', default: '' },
			{ key: 'stripDomain', label: 'Strip domain from username', type: 'checkbox', default: true },
			{ key: 'groupDomain', label: 'Create group per domain', type: 'checkbox', default: false },
		],
		// 993 is uniquely SSL/TLS (imaps); 143 is the plain/STARTTLS port
		sslPortMap: { '': 143, 'tls': 143, 'ssl': 993 },
	},
	'\\OCA\\UserExternal\\FTP': {
		label: 'FTP',
		fields: [
			{ key: 'host', label: 'FTP server', type: 'text', required: true },
			{ key: 'secure', label: 'Use FTPS (secure)', type: 'checkbox', default: false },
		],
	},
	'\\OCA\\UserExternal\\SMB': {
		label: 'SMB / Windows',
		fields: [
			{ key: 'host', label: 'SMB server', type: 'text', required: true },
		],
	},
	'\\OCA\\UserExternal\\SSH': {
		label: 'SSH',
		fields: [
			{ key: 'host', label: 'SSH server', type: 'text', required: true },
			{ key: 'port', label: 'Port', type: 'number', default: 22 },
		],
	},
	'\\OCA\\UserExternal\\BasicAuth': {
		label: 'HTTP Basic Auth',
		fields: [
			{ key: 'url', label: 'Auth URL', type: 'text', required: true },
		],
	},
	'\\OCA\\UserExternal\\WebDavAuth': {
		label: 'WebDAV',
		fields: [
			{ key: 'url', label: 'WebDAV URL', type: 'text', required: true },
		],
	},
	'\\OCA\\UserExternal\\XMPP': {
		label: 'XMPP (Prosody)',
		fields: [
			{ key: 'host', label: 'XMPP domain', type: 'text', required: true },
			{ key: 'dbName', label: 'Database name', type: 'text', required: true },
			{ key: 'dbUser', label: 'Database user', type: 'text', required: true },
			{ key: 'dbPassword', label: 'Database password', type: 'password', required: true },
			{ key: 'xmppDomain', label: 'XMPP domain filter', type: 'text', required: true },
			{ key: 'passwordHashed', label: 'Passwords are hashed', type: 'checkbox', default: true },
		],
	},
}

/** Convert named field values to the positional arguments array the PHP class expects. */
export function fieldsToArguments(cls: BackendClass, values: Record<string, unknown>): unknown[] {
	return BACKEND_SPECS[cls].fields.map(f => values[f.key] ?? f.default ?? '')
}

/** Convert a positional arguments array back to named field values. */
export function argumentsToFields(cls: BackendClass, args: unknown[]): Record<string, unknown> {
	const fields = BACKEND_SPECS[cls].fields
	return Object.fromEntries(fields.map((f, i) => [f.key, args[i] ?? f.default ?? '']))
}
