# Security Policy

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

We take the security of Laravel Cache Cascade seriously. If you discover a security vulnerability, please follow these steps:

### 1. **Do NOT Create a Public Issue**

Security vulnerabilities should **never** be reported through public GitHub issues, discussions, or pull requests.

### 2. **Email the Maintainers**

Send details to: **security@your-email.com** *(update this)*

Please include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

### 3. **Wait for Response**

You should receive an initial response within 48 hours acknowledging your report.

### 4. **Disclosure Timeline**

- **0-2 days**: Initial response and assessment
- **3-7 days**: Work on fix and testing
- **7-14 days**: Release patch version
- **14+ days**: Public disclosure

## Security Best Practices

When using Laravel Cache Cascade, follow these security guidelines:

### 1. **File Permissions**

Ensure proper file permissions for the cache directory:

```bash
chmod 755 config/dynamic
```

Files should not be publicly accessible via web server.

### 2. **Visitor Isolation**

Enable visitor isolation for user-specific data:

```php
// Prevent data leakage between users
$userData = CacheCascade::get('user.settings', [], [
    'visitor_isolation' => true
]);
```

### 3. **Sensitive Data**

- **Never cache passwords or tokens**
- Consider encrypting sensitive cached data:

```php
CacheCascade::set('sensitive', encrypt($data));
$data = decrypt(CacheCascade::get('sensitive'));
```

### 4. **Input Validation**

Always validate cache keys from user input:

```php
$key = Str::slug($request->input('key'));
$data = CacheCascade::get($key);
```

### 5. **Cache Poisoning Prevention**

- Use visitor isolation for user-submitted content
- Validate data before caching
- Set appropriate TTLs to limit exposure

### 6. **Database Security**

When using database fallback:
- Use prepared statements (handled by Eloquent)
- Validate model attributes
- Apply proper authorization checks

## Known Security Considerations

### Cache Key Injection

**Risk**: User-controlled cache keys could access unintended data.

**Mitigation**:
```php
// Bad
$data = CacheCascade::get($request->input('key'));

// Good
$allowedKeys = ['settings', 'config', 'faqs'];
$key = $request->input('key');
if (in_array($key, $allowedKeys)) {
    $data = CacheCascade::get($key);
}
```

### File Storage Security

**Risk**: PHP files in config directory could be executed if misconfigured.

**Mitigation**:
- Store files outside web root when possible
- Use `.htaccess` to deny access:

```apache
# config/dynamic/.htaccess
Deny from all
```

### Multi-tenant Data Isolation

**Risk**: Data leakage between tenants in SaaS applications.

**Mitigation**:
```php
// Always prefix with tenant ID
$tenantId = auth()->user()->tenant_id;
$key = "tenant.{$tenantId}.settings";
CacheCascade::set($key, $data);
```

## Security Updates

Security updates will be released as patch versions (e.g., 1.0.1, 1.1.1) and announced through:
- GitHub Security Advisories
- Package release notes
- Email to reporters

## Responsible Disclosure

We support responsible disclosure. Security researchers who follow this policy will be:
- Credited in the security advisory (unless anonymity requested)
- Thanked in the CHANGELOG
- Not pursued legally for their research

## Contact

For security concerns, contact:
- Email: **security@your-email.com** *(update this)*
- PGP Key: [Link to PGP key if available]

For general questions, use:
- GitHub Issues (non-security)
- GitHub Discussions

Thank you for helping keep Laravel Cache Cascade secure!