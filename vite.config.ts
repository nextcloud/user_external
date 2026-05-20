import { createAppConfig } from '@nextcloud/vite-config'
import { join } from 'path'

declare const __dirname: string

export default createAppConfig({
	adminSettings: join(__dirname, 'src', 'adminSettings.ts'),
}, {
	inlineCSS: { relativeCSSInjection: true },
	extractLicenseInformation: true,
})
