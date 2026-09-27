import NextAuth, { CredentialsSignin } from "next-auth";
import Credentials from "next-auth/providers/credentials";

const apiBaseUrl = process.env.NEXT_PUBLIC_API_URL?.replace(/\/api$/, "") ?? "http://localhost:8000";

class AdminOnlyError extends CredentialsSignin {
  code = "admin_only";
}

class RecruiterOnlyError extends CredentialsSignin {
  code = "recruiter_only";
}

class StudentOnlyError extends CredentialsSignin {
  code = "student_only";
}

export const { handlers, signIn, signOut, auth } = NextAuth({
  // Self-hosted (not Vercel): without this, `next start` rejects every request with UntrustedHost.
  trustHost: true,
  session: {
    strategy: "jwt",
  },
  pages: {
    signIn: "/auth/login",
  },
  providers: [
    Credentials({
      name: "Credentials",
      credentials: {
        email: { label: "Email", type: "email" },
        rollNo: { label: "Roll Number", type: "text" },
        password: { label: "Password", type: "password" },
        loginType: { label: "Login Type", type: "text" },
      },
      async authorize(credentials) {
        const response = await fetch(`${apiBaseUrl}/api/auth/login`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify({
            ...(credentials.rollNo
              ? { roll_no: credentials.rollNo }
              : { email: credentials.email }),
            password: credentials.password,
          }),
        });

        if (!response.ok) {
          return null;
        }

        const data = await response.json();
        const user = data?.user;

        if (!user || !data?.token) {
          return null;
        }

        const loginType = credentials?.loginType;
        if (loginType === "admin" && user.role !== "admin") {
          throw new AdminOnlyError();
        }
        if (loginType === "recruiter" && user.role !== "company") {
          throw new RecruiterOnlyError();
        }
        if (loginType === "student" && user.role !== "student") {
          throw new StudentOnlyError();
        }

        return {
          id: String(user.id),
          name: user.name,
          email: user.email,
          role: user.role,
          isSuperAdmin: Boolean(user.is_super_admin),
          companyId: user.company_id,
          rollNo: user.student_profile?.roll_no ?? null,
          accessToken: data.token,
        };
      },
    }),
  ],
  callbacks: {
    async jwt({ token, user }) {
      if (user) {
        token.role = user.role;
        token.isSuperAdmin = user.isSuperAdmin;
        token.companyId = user.companyId;
        token.rollNo = user.rollNo;
        token.accessToken = user.accessToken;
      }

      return token;
    },
    async session({ session, token }) {
      if (session.user) {
        session.user.id = token.sub ?? "";
        session.user.role = token.role as "admin" | "company" | "student";
        session.user.isSuperAdmin = Boolean(token.isSuperAdmin);
        session.user.companyId = token.companyId as number | null;
        session.user.rollNo = (token.rollNo as string | null | undefined) ?? null;
      }

      session.accessToken = token.accessToken as string | undefined;

      return session;
    },
  },
});
