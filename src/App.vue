<template>
	<div id="user-external" class="section">
    <h2 class="inlineblock">{{t('user_external', 'External User Backends')}}</h2>
  	<a target="_blank" rel="noreferrer" class="icon-info"
  	   title="Open Documentation"
  	   href="https://docs.nextcloud.com/server/latest/admin_manual/configuration_user/user_auth_ftp_smb_imap.html"></a>

  	<div id="user-external-save-indicator" class="msg success inlineblock" style="display: none;">{{t('user_external', 'Saved')}}</div>

		<form id="user-external">
			<table id="userExternal" class="grid">
				<thead>
					<tr>
						<td>Backend type</td>
						<td>Configuration</td>
						<td>Additional Configs</td>
						<td></td>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(userBackend, key) in userBackends">
						<td>{{ userBackendNames[key] }}</td>
						<td><input type="text" name="url" v-bind:value="userBackends[key]['arguments'][0]" placeholder="URL"></td>
						<td><input v-if="userBackendNames[key] === 'OC_User_IMAP'" type="text" name="domain" v-bind:value="userBackends[key]['arguments'][1]" placeholder=""></td>
						<td></td>
					</tr>
					<tr>
						<td>
							<select id="selectBackend" class="selectBackend">
								<option value="" disabled="" selected="" style="display:none;">Add External User Backend</option>
								<option value="imap">IMAP</option>
								<option value="ftp">FTP</option>
								<option value="smb">SMB/Samba</option>
								<option value="basic_auth">HTTP(S) Basic Auth</option>
								<option value="dav">WebDAV</option>
							</select>
						</td>
						<td></td>
						<td></td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</form>

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
		userBackendNames() {
			this.user_backends = [];
			for (var i in this.serverData.user_backends) {
				this.user_backends.push(this.serverData.user_backends[i]["class"]);
			}
			return this.user_backends;
		},
		userBackends() {
			return this.serverData.user_backends;
		}

	},
	methods: {


	}
};
</script>
