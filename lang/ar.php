<?php

/*
 | Arabic translations. Keys are the English text used in the code.
 | tests/Architecture/TranslationsTest.php fails when a template uses a
 | string that has no entry here.
 */

return [
    // Layout and errors
    'Lost in the list'          => 'الصفحة غير موجودة',
    'Something went wrong'      => 'حدث خطأ ما',
    'Back to the board'         => 'العودة إلى اللوحة',
    'Something broke on our side. Please try again.' => 'حدث خطأ من جهتنا. حاول مرة أخرى.',
    "This page doesn't exist."  => 'هذه الصفحة غير موجودة.',
    'This action only accepts form submissions.' => 'هذا الإجراء يقبل إرسال النماذج فقط.',
    'Your session expired. Refresh the page and try again.' => 'انتهت الجلسة. حدّث الصفحة وحاول مرة أخرى.',
    'You do not have permission to do that.' => 'ليست لديك صلاحية للقيام بذلك.',

    // Auth
    'Log in'                    => 'تسجيل الدخول',
    'Create account'            => 'إنشاء حساب',
    'Plan the week'             => 'خطة الأسبوع',
    'Gym at 7pm'                => 'اجتماع الفريق ٧ مساءً',
    'Finish the report'         => 'إنهاء التقرير',
    'Small steps,'              => 'خطوات صغيرة،',
    'every single day.'         => 'كل يوم.',
    "Your team's boards, lists and cards in one bright, simple place." => 'لوحات فريقك وقوائمه وبطاقاته في مكان واحد بسيط وواضح.',
    'Welcome back'              => 'مرحبًا بعودتك',
    'Log in to pick up where you left off.' => 'سجّل الدخول لتكمل من حيث توقفت.',
    'Email'                     => 'البريد الإلكتروني',
    'Password'                  => 'كلمة المرور',
    'Name'                      => 'الاسم',
    'Confirm password'          => 'تأكيد كلمة المرور',
    'New here?'                 => 'جديد هنا؟',
    'Create an account'         => 'أنشئ حسابًا',
    'Plan the sprint'           => 'خطة السبرنت',
    'Review the design'         => 'مراجعة التصميم',
    'Ship the release'          => 'إطلاق الإصدار',
    "Get your team's work"      => 'أخرج عمل فريقك',
    'out of your head.'         => 'من رأسك إلى لوحة واضحة.',
    'Free, private and fast. Your data stays in your own database.' => 'مجاني وخاص وسريع. بياناتك تبقى في قاعدة بياناتك.',
    'Create your account'       => 'أنشئ حسابك',
    'It takes less than a minute.' => 'يستغرق أقل من دقيقة.',
    'Already have an account?'  => 'لديك حساب بالفعل؟',
    'Too many attempts. Please wait 15 minutes and try again.' => 'محاولات كثيرة. انتظر ١٥ دقيقة ثم حاول مرة أخرى.',
    "That email and password don't match." => 'البريد الإلكتروني وكلمة المرور غير متطابقين.',
    "Passwords don't match."    => 'كلمتا المرور غير متطابقتين.',
    'This email already has an account.' => 'يوجد حساب بهذا البريد الإلكتروني.',
    'Please log in again.'      => 'سجّل الدخول مرة أخرى.',

    // Validation (":label" is the translated field name, ":param" the rule's value)
    ':label is required.'                         => 'حقل :label مطلوب.',
    ':label must be a valid date.'                => 'حقل :label يجب أن يكون تاريخًا صحيحًا.',
    ':label must be a valid email address.'       => 'حقل :label يجب أن يكون بريدًا إلكترونيًا صحيحًا.',
    ':label has an invalid value.'                => 'قيمة :label غير صالحة.',
    ':label may not be longer than :param characters.' => 'حقل :label يجب ألا يزيد عن :param حرفًا.',
    ':label must be at least :param characters.'  => 'حقل :label يجب ألا يقل عن :param أحرف.',
    ':label must be text, not just a number.'     => 'حقل :label يجب أن يكون نصًا وليس رقمًا فقط.',
];
