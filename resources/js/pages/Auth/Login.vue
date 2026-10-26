<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuestLayout from '@/layouts/GuestLayout.vue';

defineOptions({ layout: GuestLayout });

const form = useForm({
  email: '',
  password: '',
  remember: false,
});

function submit(): void {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
}
</script>

<template>
  <Head title="Masuk" />

  <Card>
    <CardHeader>
      <CardTitle>Masuk</CardTitle>
      <CardDescription>Gunakan akun yang diberikan administrator.</CardDescription>
    </CardHeader>
    <CardContent>
      <form
        class="space-y-4"
        novalidate
        @submit.prevent="submit"
      >
        <div class="space-y-1.5">
          <Label for="email">Email</Label>
          <Input
            id="email"
            v-model="form.email"
            type="email"
            class="min-h-11"
            autocomplete="username"
            autofocus
            :aria-invalid="!!form.errors.email || undefined"
            :aria-describedby="form.errors.email ? 'email-error' : undefined"
          />
          <p
            v-if="form.errors.email"
            id="email-error"
            class="text-sm text-destructive"
            role="alert"
          >
            {{ form.errors.email }}
          </p>
        </div>

        <div class="space-y-1.5">
          <Label for="password">Password</Label>
          <Input
            id="password"
            v-model="form.password"
            type="password"
            class="min-h-11"
            autocomplete="current-password"
            :aria-invalid="!!form.errors.password || undefined"
            :aria-describedby="form.errors.password ? 'password-error' : undefined"
          />
          <p
            v-if="form.errors.password"
            id="password-error"
            class="text-sm text-destructive"
            role="alert"
          >
            {{ form.errors.password }}
          </p>
        </div>

        <div class="flex min-h-11 items-center gap-2">
          <Checkbox
            id="remember"
            :model-value="form.remember"
            @update:model-value="form.remember = $event === true"
          />
          <Label for="remember">Ingat saya</Label>
        </div>

        <Button
          type="submit"
          class="min-h-11 w-full"
          :disabled="form.processing"
        >
          {{ form.processing ? 'Memproses...' : 'Masuk' }}
        </Button>
      </form>
    </CardContent>
  </Card>
</template>
