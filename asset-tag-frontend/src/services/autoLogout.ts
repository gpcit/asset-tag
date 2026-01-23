// src/services/autoLogout.ts
import { jwtDecode } from "jwt-decode";
import Swal from "sweetalert2";
import "sweetalert2/dist/sweetalert2.min.css"; // ← IMPORTANT: Add this

interface JwtPayload {
  exp: number;
  [key: string]: any;
}

let logoutTimer: ReturnType<typeof setTimeout> | null = null;

export const initAutoLogout = () => {
  console.log("🔐 initAutoLogout called");
  
  // Clear existing timer if any
  if (logoutTimer) {
    clearTimeout(logoutTimer);
    logoutTimer = null;
  }
  
  const token = localStorage.getItem("token");
  console.log("Token exists:", !!token);
  
  if (!token) {
    console.log("⚠️ No token found");
    return;
  }
  
  try {
    const decoded = jwtDecode<JwtPayload>(token);
    const now = Date.now() / 1000;
    const secondsLeft = decoded.exp - now;
    
    console.log("⏰ Token expires in:", Math.floor(secondsLeft), "seconds");
    
    if (secondsLeft <= 0) {
      console.log("❌ Token already expired");
      showLogoutAlert();
      return;
    }
    
    // Set the timeout for the remaining seconds
    logoutTimer = setTimeout(() => {
      console.log("⏰ Timer triggered!");
      showLogoutAlert();
    }, secondsLeft * 1000);
    
    console.log("✅ Logout timer set successfully");
  } catch (e) {
    console.error("❌ Error decoding token:", e);
    showLogoutAlert();
  }
};

export const clearAutoLogout = () => {
  if (logoutTimer) {
    clearTimeout(logoutTimer);
    logoutTimer = null;
    console.log("🧹 Timer cleared");
  }
};

const showLogoutAlert = () => {
  console.log("🚨 Showing logout alert");
  
  Swal.fire({
    title: "Session Expired",
    text: "Your session has expired. You will be logged out.",
    icon: "warning",
    confirmButtonText: "OK",
    allowOutsideClick: false,
    allowEscapeKey: false,
  }).then(() => {
    console.log("✅ Alert confirmed, logging out");
    logout();
  });
};

const logout = () => {
  console.log("👋 Logging out");
  clearAutoLogout();
  localStorage.removeItem("token");
  localStorage.removeItem("user");
  window.location.href = "/";
};