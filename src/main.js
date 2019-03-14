import Vue from 'vue';
import App from './App.vue';


import Tooltip from 'nextcloud-vue/dist/Directives/Tooltip';

/* global t */
// bind to window
Vue.prototype.OC = OC;
Vue.prototype.t = t;
Vue.use(Tooltip);

new Vue({
	el: '#user-external',
	render: h => h(App)
});
