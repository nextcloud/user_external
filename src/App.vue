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
	<div id="user-external" class="section">
    <h2 class="inlineblock">{{t('user_external', 'External User Backends')}}</h2>
  	<a target="_blank" rel="noreferrer" class="icon-info"
  	   title="Open Documentation"
  	   href="https://github.com/nextcloud/user_external#readme"></a>

  	<div id="user-external-save-indicator" class="msg success inlineblock" style="display: none;">{{t('user_external', 'Saved')}}</div>

		<BackendList :user_backends="user_backends"


						/>

	</div>
</template>

<script>
import axios from 'nextcloud-axios';

import BackendList from './components/BackendList.vue';

export default {
	name: 'user_external',
	components: {
		BackendList
	},
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
			serverData: [],
		};
	},
	computed: {
		user_backends() {
			return this.serverData.user_backends;
		}

	},
	methods: {
		/**
		 *
		 *
		 * @param {string} type type of the change (font or theme)
		 * @param {string} id the data of the change
		 */
		updateBackend(type, id) {
			axios.post(
					OC.linkToOCS('apps/user_external/api/v1/config', 2) + type,
					{ value: id }
				)
				.then(response => {

				})
				.catch(err => {
					console.log(err, err.response);
					OC.Notification.showTemporary(t('user_external', err.response.data.ocs.meta.message + '. Unable to apply the setting.'));
				});
		}

	}
};
</script>
