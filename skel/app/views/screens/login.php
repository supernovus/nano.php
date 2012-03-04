<form method="POST">
<?php if (isset($err) && $err == 'invalid'): ?>
<div class="error">
Invalid username or password.
</div>
<?php endif; ?>
<table>
<tr>
<td>Username</td>
<td><input type="text" name="user" /></td>
</tr>
<tr>
<td>Password</td>
<td><input type="password" name="pass" /></td>
</tr>
<tr>
<td colspan="2" style="text-align: right;">
<input type="submit" value="Login" />
</td>
</tr>
</table>
</form>
