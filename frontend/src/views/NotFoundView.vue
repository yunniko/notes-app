<template>
  <section class="not-found board-box" aria-labelledby="not-found-heading">
    <h1 id="not-found-heading" class="display">Page not found</h1>
    <p class="board-box__message">There is nothing at this address.</p>
    <div class="board-box__actions">
      <RouterLink class="chip" :to="{ name: 'notes' }">Go to your notes</RouterLink>
    </div>
  </section>
</template>

<style scoped>
.not-found {
  max-width: 40rem;
  margin: 24px auto 0;
}
.not-found .display {
  margin-bottom: 12px;
}
</style>
