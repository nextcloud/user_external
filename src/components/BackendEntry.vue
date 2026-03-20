<template>
	<div class="backend-entry">
		<div class="backend-entry__header">
			<strong>{{ spec.label }}</strong>
			<NcButton type="tertiary-no-background"
				:aria-label="t('user_external', 'Remove backend')"
				@click="$emit('remove')">
				<template #icon>
					<Close :size="20" />
				</template>
			</NcButton>
		</div>

		<div class="backend-entry__fields">
			<div v-for="field in spec.fields" :key="field.key" class="backend-entry__field">
				<label :for="`${uid}-${field.key}`">{{ field.label }}</label>

				<NcCheckboxRadioSwitch v-if="field.type === 'checkbox'"
					:id="`${uid}-${field.key}`"
					:model-value="!!values[field.key]"
					@update:model-value="update(field.key, $event)">
					{{ field.label }}
				</NcCheckboxRadioSwitch>

				<NcSelect v-else-if="field.type === 'select'"
					:id="`${uid}-${field.key}`"
					:model-value="selectOption(field)"
					:options="(field.options || []).map((o, i) => ({ id: o, label: field.optionLabels?.[i] || o || t('user_external', '(none)') }))"
					:clearable="false"
					@update:model-value="update(field.key, $event?.id)" />

				<input v-else
					:id="`${uid}-${field.key}`"
					:type="field.type === 'password' ? 'password' : field.type === 'number' ? 'number' : 'text'"
					:value="values[field.key]"
					:required="field.required"
					class="backend-entry__input"
					@input="update(field.key, ($event.target as HTMLInputElement).value)" />
			</div>
		</div>

		<div class="backend-entry__footer">
			<NcButton type="primary"
				:disabled="saving"
				@click="$emit('save')">
				{{ saving ? t('user_external', 'Saving…') : t('user_external', 'Save') }}
			</NcButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import Close from 'vue-material-design-icons/Close.vue'
import type { BackendClass, BackendSpec, FieldSpec } from '../backendSpecs.ts'

const props = defineProps<{
	uid: string
	backendClass: BackendClass
	spec: BackendSpec
	values: Record<string, unknown>
	saving: boolean
}>()

const emit = defineEmits<{
	(e: 'update', key: string, value: unknown): void
	(e: 'remove'): void
	(e: 'save'): void
}>()

function update(key: string, value: unknown) {
	emit('update', key, value)

	const map = props.spec.sslPortMap
	if (!map) return

	if (key === 'sslmode') {
		const defaultPort = map[value as string]
		if (defaultPort !== undefined) {
			emit('update', 'port', defaultPort)
		}
	} else if (key === 'port') {
		const port = Number(value)
		const matches = Object.entries(map).filter(([, p]) => p === port).map(([mode]) => mode)
		if (matches.length === 1) {
			emit('update', 'sslmode', matches[0])
		}
	}
}

function selectOption(field: FieldSpec) {
	const val = props.values[field.key] ?? field.default ?? ''
	const idx = (field.options || []).indexOf(val as string)
	const label = (idx >= 0 && field.optionLabels?.[idx]) || (val as string) || t('user_external', '(none)')
	return { id: val, label }
}
</script>

<style scoped>
.backend-entry {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 16px;
	margin-bottom: 12px;
}

.backend-entry__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 12px;
}

.backend-entry__fields {
	display: grid;
	gap: 8px;
}

.backend-entry__field {
	display: grid;
	grid-template-columns: 200px 1fr;
	align-items: center;
	gap: 8px;
}

.backend-entry__field label {
	font-weight: bold;
	color: var(--color-text-maxcontrast);
}

.backend-entry__input {
	width: 100%;
}

.backend-entry__footer {
	margin-top: 16px;
}
</style>
