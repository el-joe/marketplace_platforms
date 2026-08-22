export const deleteAccountReasons = [
  { value: "another_account", labelKey: "deleteAccountReasonAnotherAccount" },
  { value: "new_account", labelKey: "deleteAccountReasonNewAccount" },
  { value: "not_using", labelKey: "deleteAccountReasonNotUsing" },
  {
    value: "forgot_password_emails",
    labelKey: "deleteAccountReasonForgotPasswordEmails",
  },
  { value: "security_concerns", labelKey: "deleteAccountReasonSecurityConcerns" },
  { value: "privacy_concerns", labelKey: "deleteAccountReasonPrivacyConcerns" },
  { value: "other", labelKey: "deleteAccountReasonOther" },
] as const;

export type DeleteAccountReason = (typeof deleteAccountReasons)[number]["value"];
