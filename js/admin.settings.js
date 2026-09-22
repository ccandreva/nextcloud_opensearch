/*
 * FullTextSearch_OpenSearch - Use OpenSearch to index the content of your nextcloud
 *
 * @license GNU AGPL version 3 or any later version
 */
(function () {
	'use strict'

	const app = 'fulltextsearch_opensearch'
	const mount = document.getElementById('fulltextsearch-opensearch-admin-settings')
	if (!mount) {
		return
	}

	const config = OCP.InitialState.loadState(app, 'admin-config')
	const fields = [
		['opensearch_host', t(app, 'OpenSearch hosts'), 'text', t(app, 'Comma-separated HTTP(S) URLs. Stored credentials are not sent to the browser; enter credentials again only when changing this field.')],
		['opensearch_index', t(app, 'Index'), 'text', t(app, 'Name of the OpenSearch index.')],
		['fields_limit', t(app, 'Fields limit'), 'number', t(app, 'Maximum number of fields in the index.')],
		['analyzer_tokenizer', t(app, 'Analyzer tokenizer'), 'text', t(app, 'Tokenizer used by the OpenSearch analyzer.')],
	]
	const saved = {...config}
	let pendingSave = Promise.resolve()
	const settingsEvent = 'fulltextsearch:settings-admin-updated'
	const updateVisibility = (detail) => {
		section.hidden = detail?.platform !== 'open_search'
	}

	const section = document.createElement('div')
	section.className = 'section fulltextsearch-opensearch-settings'
	section.hidden = true
	const heading = document.createElement('h2')
	heading.textContent = t(app, 'OpenSearch')
	section.appendChild(heading)

	for (const [key, labelText, type, hintText] of fields) {
		const wrapper = document.createElement('div')
		wrapper.className = 'settings-field'
		const label = document.createElement('label')
		label.htmlFor = key
		label.textContent = labelText
		const input = document.createElement('input')
		input.id = key
		input.name = key
		input.type = type
		input.value = config[key]
		input.addEventListener('input', () => {
			input.removeAttribute('aria-invalid')
		})
		input.addEventListener('blur', () => saveField(key, input.value))
		const hint = document.createElement('span')
		hint.className = 'settings-hint'
		hint.textContent = hintText
		wrapper.append(label, input, hint)
		section.appendChild(wrapper)
	}

	for (const [key, labelText] of [
		['opensearch_logger_enabled', t(app, 'Enable OpenSearch logging')],
		['allow_self_signed_cert', t(app, 'Allow self-signed TLS certificates')],
	]) {
		const wrapper = document.createElement('div')
		wrapper.className = 'settings-field'
		const input = document.createElement('input')
		input.id = key
		input.name = key
		input.type = 'checkbox'
		input.checked = Boolean(config[key])
		input.addEventListener('change', () => saveField(key, input.checked))
		const label = document.createElement('label')
		label.htmlFor = key
		label.append(input, document.createTextNode(' ' + labelText))
		wrapper.appendChild(label)
		section.appendChild(wrapper)
	}

	const status = document.createElement('span')
	status.className = 'settings-status'
	status.setAttribute('aria-live', 'polite')
	section.appendChild(status)
	mount.appendChild(section)
	window.addEventListener(settingsEvent, (event) => updateVisibility(event.detail))
	updateVisibility(window.OCA?.FullTextSearch?.settings)

	function saveField(key, value) {
		// The host shown in the browser has no user information. Submitting it
		// unchanged would replace credentials stored on the server.
		if (String(value) === String(saved[key])) return
		pendingSave = pendingSave.then(() => persistField(key, value))
	}

	async function persistField(key, value) {
		status.textContent = t(app, 'Saving…')
		try {
			const response = await fetch(OC.generateUrl('/apps/fulltextsearch_opensearch/admin/settings'), {
				method: 'POST',
				headers: {'Content-Type': 'application/json', requesttoken: OC.requestToken},
				body: JSON.stringify({data: {[key]: value}}),
			})
			const result = await response.json()
			if (!response.ok) {
				if (Array.isArray(result)) {
					for (const invalidKey of result) document.getElementById(invalidKey)?.setAttribute('aria-invalid', 'true')
				}
				throw new Error('validation')
			}
			saved[key] = result[key]
			if (key === 'opensearch_host' && document.getElementById(key).value === value) {
				document.getElementById(key).value = result[key]
			}
			status.textContent = t(app, 'Saved')
		} catch (error) {
			status.textContent = error.message === 'validation'
				? t(app, 'Please correct the highlighted settings.')
				: t(app, 'Could not save the OpenSearch settings.')
		}
	}
})()
