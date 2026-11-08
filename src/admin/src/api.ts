export interface ApiConfig {
	root: string
	nonce: string
}

export interface ApiOptions {
	method?: string
	body?: unknown
	headers?: Record<string, string>
}

function config(): ApiConfig {
	const runtime = window as unknown as { pxAppointmentAdmin?: Partial<ApiConfig> }

	if (runtime.pxAppointmentAdmin?.root) {
		return {
			root: runtime.pxAppointmentAdmin.root,
			nonce: runtime.pxAppointmentAdmin.nonce || '',
		}
	}

	return { root: '/wp-json/px-appointment/v1/', nonce: '' }
}

export async function api<T = any>(path: string, options: ApiOptions = {}): Promise<T> {
	const cfg = config()
	const headers: Record<string, string> = {
		'Content-Type': 'application/json',
		...(options.headers || {}),
	}

	if (cfg.nonce) {
		headers['X-WP-Nonce'] = cfg.nonce
	}

	const response = await fetch(cfg.root.replace(/\/?$/, '/') + path.replace(/^\//, ''), {
		...options,
		headers,
		body:
			options.body && typeof options.body !== 'string'
				? JSON.stringify(options.body)
				: (options.body as string | undefined),
	})

	const text = await response.text()
	let data: any = null

	try {
		data = text ? JSON.parse(text) : null
	} catch {
		data = { message: text }
	}

	if (!response.ok) {
		const message = data?.message || data?.data?.message || data?.code || response.statusText
		const error = new Error(typeof message === 'string' ? message : response.statusText)
		;(error as any).status = response.status
		;(error as any).data = data
		throw error
	}

	return data as T
}
