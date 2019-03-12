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
						<td>{{ backendNamesReadable[key] }}</td>
						<td>
							<input type="text" name="url" v-bind:value="userBackends[key]['arguments'][0]" placeholder="URL">
						</td>
						<td>
							<span v-if="userBackendNames[key] === 'OC_User_IMAP'">Restrict login to a specific e-mail domain:<br>
								<input type="text" name="domain" v-bind:value="userBackends[key]['arguments'][1]" placeholder="">
							</span>
							<span v-if="userBackendNames[key] === 'OC_User_FTP'" id="centertext">
								<input type="checkbox" id="ftps" class="added" value="true" v-model="userBackends[key]['arguments'][1]">Use secure ftps instead of plain ftp.
							</span>
						</td>
						<td class="save"><div class="icon-checkmark" title="Save"></div></td>
					</tr>
					<tr>
						<td>
							<select id="selectBackend" class="selectBackend">
								<option value="" disabled="" selected="" style="display:none;">Add External User Backend</option>
								<option value="imap">IMAP</option>
								<option value="ftp">FTP(S)</option>
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
		},
		backendNamesShort() {
			this.backendsShort = [];
			for (var i in this.userBackendNames) {
				switch (this.userBackendNames[i]) {
				  case "OC_User_FTP":
				    this.backendsShort.push("ftp");
				    break;
				  case "OC_User_IMAP":
				    this.backendsShort.push("imap");
				    break;
				  case "OC_User_SMB":
				    this.backendsShort.push("smb");
				    break;
				  case "\OCA\User_External\WebDAVAuth":
				    this.backendsShort.push("dav");
				    break;
				  case "OC_User_BasicAuth":
				    this.backendsShort.push("basic_auth");
						break;
					default:
						this.backendsShort.push("unknown");
				}
			}
			return this.backendsShort;
		},
		backendNamesReadable() {
			this.backendsReadable = [];
			for (var i in this.backendNamesShort) {
				switch (this.backendNamesShort[i]) {
				  case "ftp":
				    this.backendsReadable.push("FTP(S)");
				    break;
				  case "imap":
				    this.backendsReadable.push("IMAP");
				    break;
				  case "smb":
				    this.backendsReadable.push("SMB/Samba");
				    break;
				  case "dav":
				    this.backendsReadable.push("WebDAV");
				    break;
				  case "basic_auth":
				    this.backendsReadable.push("HTTP(S) Basic Auth");
						break;
					default:
						this.backendsReadable.push("unknown");
				}
			}
			return this.backendsReadable;
		}

	},
	methods: {


	}
};
</script>
