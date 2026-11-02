<template>
  <section class="not-found">
    <h1>Page not found</h1>
    <p>There is nothing at this address.</p>
    <RouterLink class="button button--primary" :to="{ name: 'notes' }">Go to your notes</RouterLink>
  </section>
</template>

<style scoped>
.not-found {
  text-align: center;
  padding: 3rem 1rem;
}
</style>
