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
  <tr :data-id="user_backend.id">
    <td>
      {{ backendName }}
    </td>
    <td>
      {{ t('user_external', 'Backend Server Domain/URL/IP:') }}<br>
      <input type="text"
            ref="url"
            v-model="user_backend['arguments'][0]"
            placeholder="URL"
            @keyup.enter="rename"
            @blur="cancelRename"
            @keyup.esc="cancelRename">
    </td>
    <td>
      <span v-if="user_backend['class'] === 'OC_User_IMAP'">
        {{ t('user_external', 'Port:') }}<br>
        <input type="text"
              ref="port"
              v-model="user_backend['arguments'][1]"
              placeholder=""
              @keyup.enter="rename"
              @blur="cancelRename"
              @keyup.esc="cancelRename"><br>
        {{ t('user_external', 'Sslmode:') }}<br>
        <input type="text"
              ref="sslmode"
              v-model="user_backend['arguments'][2]"
              placeholder=""
              @keyup.enter="rename"
              @blur="cancelRename"
              @keyup.esc="cancelRename"><br>
        {{ t('user_external', 'Restrict login to a specific e-mail domain:') }}<br>
        <input type="text"
              ref="sslmode"
              v-model="user_backend['arguments'][3]"
              placeholder=""
              @keyup.enter="rename"
              @blur="cancelRename"
              @keyup.esc="cancelRename">
      </span>
      <span v-if="user_backend['class'] === 'OC_User_FTP'" id="centertext">
        <input type="checkbox"
              id="ftps"
              class="added"
              value="true"
              v-model="user_backend['arguments'][1]">
              {{ t('user_external', 'Use secure ftps instead of plain ftp.') }}
      </span>
    </td>
    <td class="save"><a v-tooltip.bottom="t('social', 'Delete')" class="icon-delete" @click.prevent="deleteBackend" /></td>
  </tr>
</template>

<script>
  import tooltip from 'nextcloud-vue/dist/Directives/Tooltip';
  export default {
    name: 'BackendEntry',
    props: {
      user_backend: {
        type: Object,
        required: true,
      }
    },
    directives: {
      tooltip
    },
    computed: {
      backendName () {
        switch (this.user_backend['class']) {
          case 'OC_User_FTP':
            return 'FTP(S)';
            break;
          case 'OC_User_IMAP':
            return 'IMAP';
            break;
          case 'OC_User_SMB':
            return 'SMB/Samba';
            break;
          case '\OCA\User_External\WebDAVAuth':
            return 'WebDAV';
            break;
          case 'OC_User_BasicAuth':
            return 'HTTP(S) Basic Auth';
            break;
          default:
            return 'unknown';
        }
      }

    },
    methods: {
      cancelRename () {

      },
      deleteBackend () {
        // Just pass it on
        this.$emit('deleteBackend', this.user_backend);
      }
    }
  }

</script>

<style lang="scss" scoped>
  .icon-delete {
    display: inline-block;
    opacity: .5;
    &:hover, &:focus {
      opacity: 1;
    }
  }
</style>
