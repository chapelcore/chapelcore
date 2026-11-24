<!--
  app.vue — the admin's root component: the API status page.
  Why: phase-1.md §6.5. It shows whether the API is up, in words (a mark beside them is
  hidden from screen readers), the admin's and the API's versions, and the API address it
  uses. It asks once when it opens and again only when someone clicks "Check again".
  How it connects: the wording comes from healthView() (app/utils/health-view.ts), the call
  from useApiClient(). Tested by test/nuxt/app.test.ts.
-->
<script setup lang="ts">
import type { HealthView } from '~/utils/health-view';

// window exists because the admin runs only in the browser (`ssr: false` in nuxt.config.ts).
const view = ref<HealthView>(CHECKING);
const running = ref(false);
const address = absoluteApiBase(useRuntimeConfig().public.apiBase, window.location.origin);
const client = useApiClient();

async function check() {
  // A click while a check is running is ignored. The button is not `disabled`, so keyboard
  // focus stays on it.
  if (running.value) {
    return;
  }
  // getHealth() never throws (it returns an error result instead), so `running` is always reset.
  running.value = true;
  view.value = CHECKING;
  view.value = healthView(await client.getHealth());
  running.value = false;
}

onMounted(check);
</script>

<template>
  <main>
    <h1>ChapelCore Admin</h1>

    <!-- The one live region: its text changes, it is never re-created, so each result is announced. -->
    <p role="status">
      <span v-if="view.mark" aria-hidden="true">{{ view.mark }}</span>
      {{ view.summary }}
    </p>
    <p v-if="view.detail">{{ view.detail }}</p>
    <p v-if="view.requestId">Reference: {{ view.requestId }}</p>

    <ul v-if="view.checks.length > 0">
      <li v-for="item in view.checks" :key="item.name">
        <span v-if="item.mark" aria-hidden="true">{{ item.mark }}</span>
        {{ item.name }}: {{ item.status }}
        <template v-if="item.message"> — {{ item.message }}</template>
      </li>
    </ul>

    <p>
      Admin version {{ buildInfo.version }}
      <template v-if="buildInfo.codename">({{ buildInfo.codename }})</template>
    </p>
    <p v-if="view.apiVersion">
      API version {{ view.apiVersion.version }}
      <template v-if="view.apiVersion.codename">({{ view.apiVersion.codename }})</template>
    </p>
    <p v-if="versionsDiffer(buildInfo, view.apiVersion)">
      The admin and the API are on different versions.
    </p>

    <p>API address: {{ address }}</p>
    <button type="button" @click="check">Check again</button>
  </main>
</template>
