import { toast } from '../../shared/toast'

export interface ApiConfig {
	root: string
	nonce: string
}

export interface ApiOptions {
	method?: string
	body?: unknown
	headers?: Record<string, string>
	toast?: false | { working?: string; success?: string }
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

async function request<T = any>(path: string, options: ApiOptions = {}): Promise<T> {
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

/** Mutations report their full lifecycle; background reads only report failures. */
export async function api<T = any>(path: string, options: ApiOptions = {}): Promise<T> {
	const { toast: feedback, ...requestOptions } = options
	const perform = () => request<T>(path, requestOptions)
	if (feedback === false) return perform()
	const method = (options.method || 'GET').toUpperCase()
	if (!['GET', 'HEAD'].includes(method)) {
		return toast.promise(perform, {
			working: feedback?.working || (method === 'DELETE' ? 'Deleting…' : 'Saving changes…'),
			success: feedback?.success || (method === 'DELETE' ? 'Deleted successfully.' : 'Changes saved successfully.'),
		})
	}
	try {
		return await perform()
	} catch (error) {
		toast.error(error instanceof Error ? error.message : 'Unable to load data. Please try again.')
		throw error
	}
}
