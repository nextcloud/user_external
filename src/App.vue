<template>
	<div id="user-external" class="section">
    <h2 class="inlineblock">{{t('user_external', 'External User Backends')}}</h2>
  	<a target="_blank" rel="noreferrer" class="icon-info"
  	   title="Open Documentation"
  	   href="https://docs.nextcloud.com/server/latest/admin_manual/configuration_user/user_auth_ftp_smb_imap.html"></a>

  	<div id="user-external-save-indicator" class="msg success inlineblock" style="display: none;">{{t('user_external', 'Saved')}}</div>

	</div>
</template>

<script>
import axios from 'nextcloud-axios';
export default {
	name: 'user_external',
	beforeMount() {
		// importing server data into the app
		const serverDataElmt = document.getElementById('serverData');
		if (serverDataElmt !== null) {
			this.serverData = JSON.parse(
				document.getElementById('serverData').dataset.server
			);
		}
	},
	data() {
		return {
			serverData: []
		};
	},
	computed: {


	},
	methods: {


	}
};
</script>
