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
	let hostChanged = false

	const section = document.createElement('div')
	section.className = 'section fulltextsearch-opensearch-settings'
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
			if (key === 'opensearch_host') hostChanged = true
		})
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
		const label = document.createElement('label')
		label.htmlFor = key
		label.append(input, document.createTextNode(' ' + labelText))
		wrapper.appendChild(label)
		section.appendChild(wrapper)
	}

	const button = document.createElement('button')
	button.type = 'button'
	button.className = 'primary'
	button.textContent = t(app, 'Save')
	const status = document.createElement('span')
	status.className = 'settings-status'
	status.setAttribute('aria-live', 'polite')
	section.append(button, status)
	mount.appendChild(section)

	button.addEventListener('click', async () => {
		button.disabled = true
		status.textContent = t(app, 'Saving…')
		const data = {}
		for (const [key] of fields) {
			if (key !== 'opensearch_host' || hostChanged) data[key] = document.getElementById(key).value
		}
		data.opensearch_logger_enabled = document.getElementById('opensearch_logger_enabled').checked
		data.allow_self_signed_cert = document.getElementById('allow_self_signed_cert').checked

		try {
			const response = await fetch(OC.generateUrl('/apps/fulltextsearch_opensearch/admin/settings'), {
				method: 'POST',
				headers: {'Content-Type': 'application/json', requesttoken: OC.requestToken},
				body: JSON.stringify({data}),
			})
			const result = await response.json()
			if (!response.ok) {
				for (const key of result) document.getElementById(key)?.setAttribute('aria-invalid', 'true')
				throw new Error('validation')
			}
			config.opensearch_host = result.opensearch_host
			document.getElementById('opensearch_host').value = result.opensearch_host
			hostChanged = false
			status.textContent = t(app, 'Saved')
		} catch (error) {
			status.textContent = error.message === 'validation'
				? t(app, 'Please correct the highlighted settings.')
				: t(app, 'Could not save the OpenSearch settings.')
		} finally {
			button.disabled = false
		}
	})
})()
