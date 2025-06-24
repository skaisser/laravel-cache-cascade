# Contributing to Laravel Cache Cascade

First off, thank you for considering contributing to Laravel Cache Cascade! It's people like you that make this package a great tool for the Laravel community.

## Code of Conduct

By participating in this project, you are expected to uphold our values:
- Be respectful and inclusive
- Welcome newcomers and help them get started
- Focus on what is best for the community
- Show empathy towards other community members

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates. When you create a bug report, include as many details as possible:

**Bug Report Template:**
```markdown
**Description**
A clear and concise description of the bug.

**To Reproduce**
Steps to reproduce the behavior:
1. Configure package with '...'
2. Call method '...'
3. See error

**Expected behavior**
What you expected to happen.

**Environment:**
- Laravel Version: [e.g., 11.0]
- PHP Version: [e.g., 8.2]
- Package Version: [e.g., 1.2.0]

**Additional context**
Add any other context, error messages, or screenshots.
```

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion, include:

- **Use case** - Explain why this enhancement would be useful
- **Current behavior** - What currently happens
- **Desired behavior** - What you want to happen
- **Possible implementation** - If you have ideas on how to implement it

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Install dependencies**: `composer install`
3. **Create your feature branch**: `git checkout -b feature/amazing-feature`
4. **Make your changes** following our coding standards
5. **Add tests** for any new functionality
6. **Run the test suite**: `composer test`
7. **Update documentation** if needed
8. **Commit your changes** using conventional commits (see below)
9. **Push to your fork**: `git push origin feature/amazing-feature`
10. **Open a Pull Request**

## Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/laravel-cache-cascade.git
cd laravel-cache-cascade

# Install dependencies
composer install

# Run tests
composer test

# Run tests with coverage
composer test -- --coverage-html coverage
```

## Coding Standards

### PHP Style

We follow PSR-12 coding standards. Key points:
- Use 4 spaces for indentation (not tabs)
- Namespace declarations must be on their own line
- Opening braces for classes and methods go on the next line
- Control structure opening braces go on the same line

### Commit Messages

We use conventional commits for clear history:

```
type(scope): subject

body (optional)

footer (optional)
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Examples:**
```
feat(commands): add cache:cascade:warm command
fix(trait): handle soft deletes in cascade invalidation
docs(readme): add Laravel 11 compatibility note
test(fake): add assertions for visitor isolation
```

### Testing

- Write tests for any new features
- Maintain or improve code coverage
- Use descriptive test method names
- Follow AAA pattern (Arrange, Act, Assert)

Example:
```php
public function test_cache_invalidation_clears_all_layers()
{
    // Arrange
    CacheCascade::set('key', 'value');
    
    // Act
    CacheCascade::invalidate('key');
    
    // Assert
    $this->assertNull(CacheCascade::get('key'));
}
```

### Documentation

- Update README.md for user-facing changes
- Add PHPDoc blocks for all public methods
- Include code examples in documentation
- Keep language clear and concise

## Testing Your Changes

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/phpunit tests/Unit/CacheCascadeManagerTest.php

# Run with coverage
composer test -- --coverage-html coverage

# Run only unit tests
vendor/bin/phpunit --testsuite Unit

# Run only feature tests
vendor/bin/phpunit --testsuite Feature
```

## Submitting Changes

### Pull Request Process

1. **Update CHANGELOG.md** with your changes under "Unreleased"
2. **Update README.md** if you've added functionality
3. **Ensure all tests pass** and coverage hasn't decreased significantly
4. **Request review** from maintainers

### Pull Request Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix (non-breaking change)
- [ ] New feature (non-breaking change)
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Tests pass locally
- [ ] Added new tests for functionality
- [ ] Existing tests updated if needed

## Checklist
- [ ] Code follows project style
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
```

## Release Process

Maintainers handle releases following semantic versioning:
- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

## Getting Help

- **Discord/Slack**: [Link if available]
- **GitHub Discussions**: Ask questions and share ideas
- **GitHub Issues**: Report bugs or request features

## Recognition

Contributors are recognized in:
- CHANGELOG.md for their specific contributions
- README.md in a contributors section
- GitHub's contributor graph

Thank you for contributing! 🎉