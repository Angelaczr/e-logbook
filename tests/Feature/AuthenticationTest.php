<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('inertia.testing.ensure_pages_exist', false);
    $this->user = User::factory()->create(['email' => 'uji@elogbook.test']);
});

it('mengarahkan / ke login untuk tamu dan ke logbook untuk yang sudah masuk', function () {
    $this->get('/')->assertRedirect('/login');
    $this->actingAs($this->user)->get('/')->assertRedirect('/logbooks');
});

it('menampilkan halaman login untuk tamu dan mengalihkan yang sudah masuk', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    $this->actingAs($this->user)->get('/login')->assertRedirect();
});

it('masuk dengan kredensial benar dan menuju logbook secara bawaan', function () {
    $this->post('/login', ['email' => 'uji@elogbook.test', 'password' => 'password'])
        ->assertRedirect(route('logbooks.index'));

    $this->assertAuthenticatedAs($this->user);
});

it('kembali ke halaman tujuan semula setelah masuk', function () {
    $this->get('/reviews')->assertRedirect('/login');

    $this->post('/login', ['email' => 'uji@elogbook.test', 'password' => 'password'])
        ->assertRedirect(url('/reviews'));
});

it('menolak kredensial salah dengan pesan Indonesia', function () {
    $this->from('/login')->post('/login', ['email' => 'uji@elogbook.test', 'password' => 'salah'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Email atau password tidak sesuai.']);

    $this->assertGuest();
});

it('memvalidasi isian login', function () {
    $this->from('/login')->post('/login', ['email' => 'bukan-email', 'password' => ''])
        ->assertSessionHasErrors(['email' => 'Format email tidak valid.', 'password' => 'Password wajib diisi.']);
});

it('menolak akun nonaktif', function () {
    $this->user->forceFill(['is_active' => false])->save();

    $this->from('/login')->post('/login', ['email' => 'uji@elogbook.test', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Akun ini sedang dinonaktifkan.']);

    $this->assertGuest();
});

it('keluar dan kembali ke login', function () {
    $this->actingAs($this->user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});
