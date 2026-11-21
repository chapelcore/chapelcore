<!--
  app.vue — the web's root component.
  Why: the skeleton's page (checkpoint 1): the site name from the build settings and the
  version from version.json.
  How it connects: Nuxt prerenders it to static HTML; checkpoint 5 makes it the coming-soon
  placeholder.
-->
<script setup lang="ts">
const { siteName } = useRuntimeConfig().public;
</script>

<template>
  <main>
    <h1>{{ siteName }}</h1>
  </main>
  <footer>
    <p>Version {{ buildInfo.version }}</p>
  </footer>
</template>
