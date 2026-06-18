import "next-auth";
import "next-auth/jwt";

declare module "next-auth" {
  interface Session {
    accessToken?: string;
    user: {
      id: string;
      role: "admin" | "company";
      isSuperAdmin?: boolean;
      companyId?: number | null;
      name?: string | null;
      email?: string | null;
      image?: string | null;
    };
  }

  interface User {
    role: "admin" | "company";
    isSuperAdmin?: boolean;
    companyId?: number | null;
    accessToken?: string;
  }
}

declare module "next-auth/jwt" {
  interface JWT {
    role?: "admin" | "company";
    isSuperAdmin?: boolean;
    companyId?: number | null;
    accessToken?: string;
  }
}
