declare module 'qrcode' {
  const QRCode: {
    toDataURL(text: string, options?: any): Promise<string>;
  };
  export = QRCode;
}
