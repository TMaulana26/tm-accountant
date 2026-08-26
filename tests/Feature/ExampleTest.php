<?php

test('the application returns a redirect to admin', function () {
    $response = $this->get('/');

    $response->assertRedirect('/admin');
});
