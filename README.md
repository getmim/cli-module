# cli-module

Module cli tools untuk bekerja dengan module.

## Instalasi

Jalankan perintah di bawah di folder instalasi tools cli:

```
mim app install cli-module
```

## Perintah

```bash
mim module init
mim module controller (name)
mim module helper (name)
mim moduel git
mim module library (name)
mim module middleware (name)
mim module model (name)
mim module service (name)
mim module watch (host|dir)
mim module sync (host|dir)
```

Perintah `mim module watch [host|dir]` dan `mim module sync [host|dir]`
menggunakan method sync yang sama dengan perintah `mim app update [module]`
dengan sumber data dari folder yang sedang aktif.