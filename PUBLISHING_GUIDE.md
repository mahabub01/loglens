# GitHub & Packagist Publishing Guide (গিটহাব ও প্যাকেজিস্ট পাবলিশিং গাইড)

এই গাইডে বিস্তারিতভাবে দেখানো হয়েছে কীভাবে **LogLens** প্যাকেজটিকে GitHub-এ আপলোড করবেন এবং **Packagist.org**-এ সাবমিট করবেন, যাতে যেকেউ বা আপনি যেকোনো Laravel প্রজেক্টে সরাসরি নিচের কমান্ড দিয়ে ইন্সটল করতে পারেন:

```bash
composer require mehrabhossain/loglens
```

---

## 📋 প্রাক-প্রস্তুতি (Prerequisites)

1. একটি **GitHub** অ্যাকাউন্ট ([github.com](https://github.com)).
2. একটি **Packagist** অ্যাকাউন্ট ([packagist.org](https://packagist.org) - সরাসরি GitHub দিয়ে লগইন করা যায়).
3. আপনার মেশিনে **Git** ইন্সটল থাকা।

---

## 🚀 ধাপ ১: GitHub-এ নতুন রিপোজিটরি তৈরি করুন

1. ব্রাউজারে [GitHub.com](https://github.com)-এ যান এবং লগইন করুন।
2. উপরে ডানপাশে **`+`** আইকনে ক্লিক করে **New repository** সিলেক্ট করুন।
3. সেটিংসগুলো নিচের মতো দিন:
   - **Repository name:** `loglens`
   - **Description:** `Developer-focused application log analyzer and monitoring dashboard for Laravel 10, 11, 12, and 13`
   - **Visibility:** **Public** (পাবলিক নির্বাচন করুন, কারণ Packagist ফ্রিতে পাবলিক প্যাকেজ হোস্ট করে)
   - **Initialize this repository with:** কোনো চেকবক্সে টিক দেবেন **না** (Add README, .gitignore, license খালি রাখুন, কারণ এগুলো আমাদের প্রজেক্টে তৈরি করা আছে)।
4. **Create repository** বাটনে ক্লিক করুন।
5. আপনার তৈরি হওয়া রিপোজিটরির লিংকটি কপি করুন:
   `https://github.com/<your-username>/loglens.git`

---

## 💻 ধাপ ২: লোকাল কোড Git-এ কমিট ও GitHub-এ পুশ করুন

আপনার কম্পিউটারের টার্মিনাল বা PowerShell ওপেন করুন এবং প্যাকেজ ফোল্ডারে যান (`D:\projects\monitoring_package`):

```bash
# ১. গিট ইনিশিয়ালাইজ করুন (যদি আগে না করা থাকে)
git init

# ২. সব ফাইল স্টেজিংয়ে যুক্ত করুন
git add .

# ৩. প্রথম কমিট করুন
git commit -m "feat: initial release of LogLens v1.0.0"

# ৪. ডিফল্ট ব্রাঞ্চকে main করুন
git branch -M main

# ৫. আপনার গিটহাব রিপোজিটরির লিংক রিমোট হিসেবে যুক্ত করুন
# (লক্ষ্য করুন: <your-username> এর জায়গায় আপনার আসল গিটহাব ইউজারনেম দিন)
git remote add origin https://github.com/<your-username>/loglens.git

# ৬. গিটহাবে কোড পুশ করুন
git push -u origin main
```

---

## 🏷️ ধাপ ৩: রিলিজ ট্যাগ (Release Tag) তৈরি করুন (খুব গুরুত্বপূর্ণ)

Composer প্যাকেজের নির্দিষ্ট ভার্সন (যেমন `1.0.0`) শনাক্ত করতে Git Tag ব্যবহার করে। তাই প্রথম ভার্সনের জন্য ট্যাগ পুশ করতে হবে:

```bash
# v1.0.0 ট্যাগ তৈরি করুন
git tag v1.0.0

# ট্যাগটি গিটহাবে পুশ করুন
git push origin v1.0.0
```

---

## 📦 ধাপ ৪: Packagist.org-এ প্যাকেজ সাবমিট করুন

1. ব্রাউজারে [Packagist.org](https://packagist.org)-এ যান।
2. উপরে ডানপাশে **Login**-এ ক্লিক করে **Login with GitHub** চাপুন (এতে সহজে অথেনটিকেশন হয়ে যাবে)।
3. লগইন করার পর উপরে মেনু থেকে **"Submit"** বাটনে ক্লিক করুন (অথবা যান [packagist.org/packages/submit](https://packagist.org/packages/submit)).
4. **Repository URL** বক্সে আপনার GitHub রিপোজিটরি লিংক দিন:
   ```text
   https://github.com/<your-username>/loglens
   ```
5. **Check** বাটনে ক্লিক করুন।
   - Packagist আপনার `composer.json` ফাইল পড়ে প্যাকেজের নাম প্রদর্শন করবে: `mehrabhossain/loglens`.
6. এরপর সবুজ রঙের **Submit** বাটনে ক্লিক করুন।

🎉 **আলহামদুলিল্লাহ! আপনার প্যাকেজটি এখন বিশ্বজুড়ে অফিসিয়ালি লাইভ এবং উন্মুক্ত!**

---

## 🔄 ধাপ ৫: অটো-আপডেট সেটআপ (GitHub Webhook)

যাতে ভবিষ্যতে আপনি গিটহাবে নতুন কোড বা ট্যাগ পুশ করলেই Packagist নিজে থেকেই আপডেট নিয়ে নেয়:

1. Packagist-এ আপনার প্যাকেজ পেজে একটি সতর্কতা দেখতে পাবেন: *"Set up the GitHub Service Hook to automatically update your package."*
2. আপনার Packagist প্রোফাইল মেনু থেকে **API Token** কপি করে নিন।
3. GitHub-এ আপনার `loglens` রিপোজিটরিতে যান -> **Settings** -> **Webhooks** -> **Add webhook**.
4. **Payload URL**-এ দিন: `https://packagist.org/api/github?username=<your-packagist-username>`
5. **Secret**-এ আপনার Packagist API Token দিন।
6. **Content type** দিন: `application/json`
7. **Just the push event** সিলেক্ট রেখে **Add webhook** চাপুন।

এখন থেকে প্রতিবার `git push` বা নতুন ট্যাগ পুশ করলেই Packagist স্বয়ংক্রিয়ভাবে আপডেট হয়ে যাবে!

---

## ✅ ধাপ ৬: টেস্ট করুন (ইন্সটলেশন টেস্ট)

এখন যেকোনো সাধারণ Laravel প্রজেক্টের টার্মিনালে প্রবেশ করে টেস্ট করুন:

```bash
# প্যাকেজ ইন্সটল
composer require mehrabhossain/loglens

# কনফিগ পাবলিশ
php artisan vendor:publish --tag=loglens-config
```

---

## 🛠️ ভবিষ্যতে নতুন ভার্সন (যেমন v1.0.1 বা v1.1.0) রিলিজ করার নিয়ম

ভবিষ্যতে যখনই প্যাকেজে কোনো বাগ ফিক্স বা নতুন ফিচার যোগ করবেন, নিচের সাধারণ ৩টি কমান্ড দিলেই নতুন ভার্সন রিলিজ হয়ে যাবে:

```bash
# ১. কোড পরিবর্তন করার পর কমিট ও পুশ
git add .
git commit -m "fix: improved performance and updated docs"
git push origin main

# ২. নতুন ভার্সন ট্যাগ তৈরি ও পুশ (যেমন v1.0.1)
git tag v1.0.1
git push origin v1.0.1
```

ব্যাস! Packagist নিজে থেকেই `v1.0.1` পেয়ে যাবে এবং ইউজাররা `composer update` করলেই নতুন আপডেট পেয়ে যাবে।
