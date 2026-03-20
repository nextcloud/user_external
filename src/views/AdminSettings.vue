<template>
	<div class="user-external-admin">
	<NcSettingsSection :name="t('user_external', 'External authentication backends')"
		:description="t('user_external', 'Configure the external services used to authenticate users. Changes are written to config.php and take effect immediately.')">

		<BackendEntry v-for="(backend, index) in backends"
			:key="index"
			:uid="`backend-${index}`"
			:backend-class="backend.class"
			:spec="BACKEND_SPECS[backend.class]"
			:values="backend.fields"
			:saving="saving"
			@update="(key, val) => updateField(index, key, val)"
			@remove="removeBackend(index)"
			@save="save" />

		<div v-if="backends.length === 0" class="backends-empty">
			{{ t('user_external', 'No backends configured. Add one below.') }}
		</div>

		<div class="backends-actions">
			<NcSelect
				:options="backendOptions"
				:placeholder="t('user_external', 'Select backend type to add…')"
				label="label"
				:clearable="false"
				class="backends-actions__select"
				@update:model-value="addBackend" />
		</div>

	</NcSettingsSection>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import BackendEntry from '../components/BackendEntry.vue'
import {
	BACKEND_SPECS,
	argumentsToFields,
	fieldsToArguments,
	type BackendClass,
} from '../backendSpecs.ts'

// -------------------------------------------------------------------------
// State
// -------------------------------------------------------------------------

interface RawBackend {
	class: BackendClass
	arguments: unknown[]
}

interface BackendState {
	class: BackendClass
	fields: Record<string, unknown>
}

const rawBackends = loadState<RawBackend[]>('user_external', 'backends', [])

const backends = ref<BackendState[]>(
	rawBackends.map(b => ({
		class: b.class,
		fields: argumentsToFields(b.class, b.arguments),
	})),
)

const saving = ref(false)

// -------------------------------------------------------------------------
// Derived data
// -------------------------------------------------------------------------

const backendOptions = Object.entries(BACKEND_SPECS).map(([cls, spec]) => ({
	id: cls as BackendClass,
	label: spec.label,
}))

// -------------------------------------------------------------------------
// Actions
// -------------------------------------------------------------------------

function addBackend(option: { id: BackendClass; label: string } | null) {
	if (!option) return
	const fields = Object.fromEntries(
		BACKEND_SPECS[option.id].fields.map(f => [f.key, f.default ?? '']),
	)
	backends.value.push({ class: option.id, fields })
}

function removeBackend(index: number) {
	backends.value.splice(index, 1)
}

function updateField(index: number, key: string, value: unknown) {
	backends.value[index].fields[key] = value
}

async function save() {
	saving.value = true
	try {
		const payload: RawBackend[] = backends.value.map(b => ({
			class: b.class,
			arguments: fieldsToArguments(b.class, b.fields),
		}))
		await axios.put(generateUrl('/apps/user_external/api/v1/backends'), { backends: payload })
		showSuccess(t('user_external', 'Settings saved.'))
	} catch (e) {
		showError(t('user_external', 'Failed to save settings.'))
		console.error(e)
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
.user-external-admin {
	padding: 16px;
}

.backends-empty {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.backends-actions {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-top: 16px;
}

.backends-actions__select {
	min-width: 220px;
}
</style>
