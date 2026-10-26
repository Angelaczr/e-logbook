<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import ReviewDetail from '@/components/review/ReviewDetail.vue';
import { reviewRoutes } from '@/lib/routes';
import type { LogbookDetail } from '@/types/logbook';

defineProps<{
  logbook: LogbookDetail;
}>();
</script>

<template>
  <Head :title="`Review ${logbook.owner.name}`" />

  <div class="space-y-4">
    <Button
      as-child
      variant="ghost"
      class="min-h-11"
    >
      <Link :href="reviewRoutes.index">
        ← Kembali ke daftar review
      </Link>
    </Button>
    <Card>
      <CardContent class="pt-6">
        <ReviewDetail
          :logbook="logbook"
          @done="router.get(reviewRoutes.index)"
        />
      </CardContent>
    </Card>
  </div>
</template>
