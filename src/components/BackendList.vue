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
                  @deleteBackend="deleteBackend"
                  @changeBackend="changeBackend"/>
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

    },
    methods: {
      changeBackend () {
        this.$emit('changeBackend');
      },
      cancelChange () {

      },
      rename (user_backend, newName) {
        this.$emit('rename', token, newName);
      },
      deleteBackend (user_backend) {
        // Just pass it on
        this.$emit('deleteBackend', user_backend);
      }

    }

  }

</script>
