<template>
  <select id="selectBackend" class="selectBackend" v-on:change="addBackend" v-model="selection">
    <option value="" selected="" style="display:none;">Add External User Backend</option>
    <option value="imap">IMAP</option>
    <option value="ftp">FTP(S)</option>
    <option value="smb">SMB/Samba</option>
    <option value="basic_auth">HTTP(S) Basic Auth</option>
    <option value="dav">WebDAV</option>
  </select>
</template>

<script>

  export default {
    name: 'BackendSetupDialogue',
    props: {
      add: {
        type: Function,
        required: true,
      }
    },
    data () {
      return {
        selection: ''
      }
    },
    methods: {
      addBackend() {
        console.log(this.selection)
        this.$emit('addBackend', this.selection)
        selection: ''
      }
    }

  }
</script>
