# VtPhp documentation

Welcome! These guides take you from installing VtPhp to running it in
production. They're written for newcomers: each one starts with the basics
and builds up, and explains new terms as it goes.

If you're new, read the first three pages in order. After that, jump to
whatever you need.

## 1. Start here

1. [Getting started](getting-started.md): install, run the app, send your
   first request.
2. [Your first API](your-first-api.md): build a small notes API step by step.
3. [How a request works](request-lifecycle.md): what happens between a
   request arriving and your code running.

## 2. The basics

- [Routing](routing.md): connect URLs to your code.
- [Controllers](controllers.md): the code that answers each request.
- [Validation](validation.md): check what callers send you.
- [Resources](resources.md): shape the JSON you send back.
- [Models](models.md): read and write the database.
- [Migrations and seeders](migrations.md): create and change tables.

## 3. Building a real app

- [Authentication](authentication.md): sign-up, login, logout, API tokens and protected routes.
- [Authorization](authorization.md): decide who may do what, with policies and gates.
- [Sessions, cookies and cache](sessions-cookies-cache.md): remember things between requests.
- [Mail](mail.md): send emails.
- [Views](views.md): templates for emails and pages.
- [Middleware](middleware.md): run checks on many routes at once.
- [CORS](cors.md): let your website or app on another domain call the API.

## 4. Going to production

- [Security](security.md): what's protected for you, and what to configure.
- [Errors](exceptions.md): how errors are reported and how to add your own.
- [Testing](testing.md): check your code automatically.
- [Deploying](deploying.md): put your app on a real server.

## 5. Reference and advanced topics

- [The forge CLI](cli.md): every `php forge` command.
- [Container and service providers](container.md): how VtPhp creates objects for you.

## Words you'll see often

| Word | Meaning |
| --- | --- |
| **API** | A program other programs talk to over the web, usually in JSON. |
| **Endpoint** | One URL + method your API answers, like `POST /api/v1/login`. |
| **JSON** | A text format for data: `{"title": "Buy milk"}`. |
| **Request / response** | What the caller sends / what your API sends back. |
| **Status code** | A number in every response: `200` OK, `201` created, `401` not logged in, `404` not found, `422` invalid input, `500` server error. |
| **Route** | A rule connecting an endpoint to your code. |
| **Controller** | A class whose methods handle requests. |
| **Model** | A class that represents a database table. |
| **Migration** | A file that creates or changes database tables. |
| **Middleware** | Code that runs before/after controllers for many routes. |
| **Policy** | A class of rules about who may do what with a model. |
| **`.env`** | Your private settings file. |

The design notes behind the framework (goals, architecture and roadmap) are
in [php_api_framework_blueprint.md](php_api_framework_blueprint.md). You don't
need them to use VtPhp.
