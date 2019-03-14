<!--
  - @copyright 2019 Jonas Sulzer <jonas@violoncello.ch>
  -
  - @author 2019 Jonas Sulzer <jonas@violoncello.ch>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
  <form id="user-external">
    <table id="userExternal" class="grid">
      <thead>
        <tr>
          <td>{{ t('user_external', 'Backend type') }}</td>
          <td>{{ t('user_external', 'Configuration') }}</td>
          <td>{{ t('user_external', 'Additional Configs') }}</td>
          <td></td>
        </tr>
      </thead>
      <tbody>
        <BackendEntry v-for="user_backend in user_backends"
                  :key="user_backend.id"
                  :user_backend="user_backend"
                  @rename="rename"
                  @delete="onDelete"/>
      </tbody>
    </table>
  </form>
</template>


<script>
  import BackendEntry from './BackendEntry.vue';

  export default {
    name: 'BackendList',
    components: {
      BackendEntry
    },
    props: {
      user_backends: {
        type: Array,
        required: true,
      },
    },
    computed: {
      userBackendNames() {
        this.user_backends = [];
        for (var i in this.serverData.user_backends) {
          this.user_backends.push(this.serverData.user_backends[i]["class"]);
        }
        return this.user_backends;
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
      rename (user_backend, newName) {
        this.$emit('rename', token, newName);
      },
      onDelete (token) {
        // Just pass it on
        this.$emit('delete', token);
      }

    }

  }

</script>
