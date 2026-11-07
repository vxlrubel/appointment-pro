

<script setup lang="ts">
const handleClick = () => {
  console.log('Button clicked!');
};

</script>



<template>
  <div>
    <h1>This is an about page</h1>

	<button @click="handleClick">Click me</button>
  </div>
</template>


