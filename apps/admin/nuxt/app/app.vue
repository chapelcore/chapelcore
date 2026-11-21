<!--
  app.vue — the admin's root component.
  Why: the skeleton's page (checkpoint 1): the app's name and its version from version.json.
  How it connects: Nuxt mounts it under /admin/; checkpoint 3 adds the API status.
-->
<template>
  <main>
    <h1>ChapelCore Admin</h1>
    <p>Version {{ buildInfo.version }}</p>
  </main>
</template>
