# LogLens ড্যাশবোর্ড অ্যাক্সেস নিয়ন্ত্রণ ও নিরাপত্তা নির্দেশিকা (Access Control & Security Guide)

এই ডকুমেন্টেশনে বিস্তারিত আলোচনা করা হয়েছে কীভাবে **LogLens** ড্যাশবোর্ডের URL নিরাপদ ও লক করে রাখবেন, যাতে অননুমোদিত কোনো ইউজার বা বহিরাগত ব্যক্তি সরাসরি URL টাইপ করে ড্যাশবোর্ডে প্রবেশ করতে না পারে।

---

## 📑 সূচিপত্র (Table of Contents)

1. [কেন ডিফল্টভাবে URL দিলে ড্যাশবোর্ড দেখা যায়?](#-কেন-ডিফল্টভাবে-url-দিলে-ড্যাশবোর্ড-দেখা-যায়)
2. [পদ্ধতি ১: নির্দিষ্ট অ্যাডমিন ছাড়া সবার জন্য লক করা (LogLens::auth) - রিকমেন্ডেড](#-পদ্ধতি-১-নির্দিষ্ট-অ্যাডমিন-ইউজার-ছাড়া-সবার-জন্য-লক-করা-loglensauth)
3. [পদ্ধতি ২: লগইন পেজে স্বয়ংক্রিয় রিডাইরেক্ট করা (auth Middleware)](#-পদ্ধতি-২-সরাসরি-লগইন-পেজে-রিডাইরেক্ট-করা-auth-middleware)
4. [পদ্ধতি ৩: লোকাল পরিবেশেও অটো-অ্যাক্সেস সম্পূর্ণ বন্ধ করা](#-পদ্ধতি-৩-লোকাল-পরিবেশেও-সরাসরি-অ্যাক্সেস-বন্ধ-করা)
5. [পদ্ধতি ৪: লারাভেল গেট ও রোল সিস্টেম (যেমন Spatie / Admin Role)](#-পদ্ধতি-৪-লারাভেল-গেট-ও-রোল-সিস্টেম-spatie--custom-role)
6. [পদ্ধতি ৫: আইপি অ্যাড্রেস (IP Whitelist) দিয়ে লক করা](#-পদ্ধতি-৫-আইপি-অ্যাড্রেস-ip-whitelist-দিয়ে-লক-করা)
7. [পদ্ধতি ৬: ডেস্ট্রাক্টিভ অ্যাকশন (লগ মোছা) আলাদাভাবে সুরক্ষিত রাখা](#-পদ্ধতি-৬-ডেস্ট্রাক্টিভ-অ্যাকশন-লগ-মোছা-আলাদাভাবে-সুরক্ষিত-রাখা)
8. [প্রোডাকশন সিকিউরিটি চেকলিস্ট](#-প্রোডাকশন-সিকিউরিটি-চেকলিস্ট)

---

## ❓ কেন ডিফল্টভাবে URL দিলে ড্যাশবোর্ড দেখা যায়?

যখন আপনার প্রজেক্টের `.env` ফাইলে:
```env
APP_ENV=local
```
থাকে, তখন ডেভেলপারদের কাজের সুবিধার্থে এবং বারবার লগইন করার ঝামেলা এড়াতে LogLens লোকাল পরিবেশে সরাসরি ড্যাশবোর্ড দেখার সুযোগ দেয় (`allow_local_by_default = true`)।

তবে আপনি চাইলে খুব সহজেই এটিকে শতভাগ লক করে রাখতে পারেন।

---

## 🛡️ পদ্ধতি ১: নির্দিষ্ট অ্যাডমিন ইউজার ছাড়া সবার জন্য লক করা (`LogLens::auth`)

এটি সবচেয়ে নির্ভরযোগ্য এবং জনপ্রিয় পদ্ধতি। অনুমোদিত ইমেইল তালিকা ছাড়া যেকেউ ঢুকলে তাকে **`403 Unauthorized`** দিয়ে আটকে দেওয়া হবে।

### ধাপ:
আপনার লারাভেল প্রজেক্টের `app/Providers/AppServiceProvider.php` ওপেন করুন এবং `boot()` মেথডে নিচের কোডটি লিখুন:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MehrabHossain\LogLens\Facades\LogLens;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ড্যাশবোর্ড অ্যাক্সেস রেস্ট্রিকশন
        LogLens::auth(function ($request) {
            // ১. ইউজারকে অবশ্যই লগইন থাকতে হবে
            $user = $request->user();
            if (! $user) {
                return false;
            }

            // ২. ইউজারের ইমেইল অনুমোদিত লিস্টে থাকতে হবে
            return in_array($user->email, [
                'admin@yourdomain.com',
                'lead-developer@yourdomain.com',
                'devops@yourdomain.com',
            ]);
        });
    }
}
```

> **ফলাফল:** এখন লগইন ছাড়া কোনো সাধারণ ভিজিটর বা অন্য কোনো ইউজার `/loglens` ইউআরএলে প্রবেশ করলে সাথে সাথে আটকে দেওয়া হবে।

---

## 🚪 পদ্ধতি ২: সরাসরি লগইন পেজে রিডাইরেক্ট করা (`auth` Middleware)

যদি চান কোনো আন-অথেনটিকেটেড ইউজার `/loglens` এ ক্লিক করলে তাকে আটকে না রেখে সরাসরি আপনার ওয়েবসাইটের **লগইন পেজে (`/login`) রিডাইরেক্ট** করে দেওয়া হোক:

### ধাপ:
আপনার প্রজেক্টের `config/loglens.php` ফাইলে যান এবং `middleware` অ্যারেতে লারাভেলের নিজস্ব `'auth'` মিডলওয়্যার যুক্ত করে দিন:

```php
// config/loglens.php

'middleware' => [
    'web',
    'auth', // <-- এটি যোগ করুন
],
```

> **ফলাফল:** এখন লগইন ছাড়া কেউ URL টাইপ করলে স্বয়ংক্রিয়ভাবে লগইন পেজে চলে যাবে। লগইন করার পর ড্যাশবোর্ডে প্রবেশ করতে পারবে।

---

## 🚫 পদ্ধতি ৩: লোকাল পরিবেশেও সরাসরি অ্যাক্সেস বন্ধ করা

লোকাল হোস্টে (`APP_ENV=local`) টেস্টিং চলাকালীনও যদি চান কেউ যেন পারমিশন বা লগইন ছাড়া দেখতে না পারে:

### ধাপ:
আপনার `config/loglens.php` ফাইলে `allow_local_by_default` অপশনটি `false` করে দিন:

```php
// config/loglens.php

'auth' => [
    'gate' => 'viewLogLens',
    'destructive_gate' => 'manageLogLens',
    'allow_local_by_default' => false, // <-- true এর বদলে false দিন
],
```

---

## 👑 পদ্ধতি ৪: লারাভেল গেট ও রোল সিস্টেম (Spatie / Custom Role)

যদি আপনার অ্যাপ্লিকেশনে Spatie Laravel-Permission বা নিজস্ব ইউজার রোল ফিল্ড (`$user->role` / `$user->is_admin`) থাকে, তবে গেটের মাধ্যমে তা নিয়ন্ত্রণ করতে পারেন:

### ধাপ:
আপনার `app/Providers/AuthServiceProvider.php` (অথবা Laravel 11/12-এর `AppServiceProvider.php`)-তে লিখুন:

```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    // ভিউ করার অনুমতি (Read-Only)
    Gate::define('viewLogLens', function ($user) {
        // Spatie ব্যবহার করলে:
        return $user->hasRole('admin') || $user->hasRole('super-admin');

        // অথবা সাধারণ কলাম ব্যবহার করলে:
        // return $user->is_admin === true;
    });
}
```

---

## 🌐 পদ্ধতি ৫: আইপি অ্যাড্রেস (IP Whitelist) দিয়ে লক করা

যদি চান ড্যাশবোর্ডটি শুধুমাত্র আপনার অফিসের আইপি, ডেভেলপার মেশিনের আইপি অথবা নির্দিষ্ট ভিপিএন (VPN) নেটওয়ার্ক থেকে দেখা যাবে:

```php
use MehrabHossain\LogLens\Facades\LogLens;

public function boot(): void
{
    LogLens::auth(function ($request) {
        $allowedIps = [
            '127.0.0.1',
            '103.145.120.45', // আপনার অফিসের স্ট্যাটিক আইপি
            '192.168.1.50',
        ];

        return in_array($request->ip(), $allowedIps, true);
    });
}
```

---

## 🔒 পদ্ধতি ৬: ডেস্ট্রাক্টিভ অ্যাকশন (লগ মোছা) আলাদাভাবে সুরক্ষিত রাখা

লগ ফাইল ভিউ করা এবং লগ ফাইল মুছে ফেলা বা ট্রাঙ্কেট করা সম্পূর্ণ আলাদা বিষয়। আপনি চাইলে সাধারণ অ্যাডমিনদের শুধু **দেখার অনুমতি** দিতে পারেন, কিন্তু **মুছে ফেলার অনুমতি** শুধুমাত্র চিফ আর্কিটেক্ট বা সুপার অ্যাডমিনকে দিতে পারেন:

```php
use MehrabHossain\LogLens\Facades\LogLens;

public function boot(): void
{
    // ১. সাধারণ ভিউ পারমিশন (সব ডেভেলপার দেখতে পারবে)
    LogLens::auth(function ($request) {
        return $request->user()?->hasRole('developer');
    });

    // ২. লগ ফাইল ডিলিট বা ক্লিয়ার করার পারমিশন (শুধুমাত্র সুপার অ্যাডমিন)
    LogLens::destructiveAuth(function ($request) {
        return $request->user()?->email === 'superadmin@yourdomain.com';
    });
}
```

এবং `.env` ফাইলে নিশ্চিত করুন:
```env
LOGLENS_ALLOW_DESTRUCTIVE=true
```

---

## ✅ প্রোডাকশন সিকিউরিটি চেকলিস্ট

প্রোডাকশন সার্ভারে LogLens ডেপ্লয় করার পূর্বে নিচের ৫টি বিষয় মিলিয়ে নিন:

1. [ ] **`LOGLENS_ENABLED=true`** দেওয়া আছে কি না (ডিফল্টভাবে বন্ধ থাকে)।
2. [ ] **`AppServiceProvider`**-এ `LogLens::auth()` অথবা `viewLogLens` গেট ডিফাইন করা হয়েছে।
3. [ ] ডেস্ট্রাক্টিভ ফাইল মোছার অনুমতি শুধুমাত্র নির্ভরযোগ্য ইউজারের জন্য সংরক্ষিত আছে (`LOGLENS_ALLOW_DESTRUCTIVE=false` ডিফল্ট রাখা ভালো)।
4. [ ] যদি লগইন রিডাইরেক্ট চান, তবে `config/loglens.php`-তে `'auth'` মিডলওয়্যার যুক্ত করা হয়েছে।
5. [ ] আপনার ড্যাশবোর্ডের URL পরিবর্তন করতে চাইলে `.env`-তে কাস্টম পাথ দিতে পারেন:
   ```env
   LOGLENS_PATH=my-secret-monitoring-portal
   ```
   (তখন ড্যাশবোর্ডের ঠিকানা হবে: `https://yourdomain.com/my-secret-monitoring-portal`)

---

<p align="center">
  LogLens v1.0.0 &bull; Enterprise-Grade Security for Laravel Applications
</p>
