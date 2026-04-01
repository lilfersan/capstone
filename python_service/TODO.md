# BERT + RandomForest Hybrid Model TODO - TORCH DLL ISSUE

## Steps
1. [x] Update requirements.txt (sentence-transformers==3.1.1)
2. [x] pip install deps (torch 2.0.1+cpu installed)
3. [x] Edit model.py (hybrid TF-IDF + BERT)
4. [!] Train `python model.py` - BLOCKED: torch DLL WinError 1114 c10.dll
5. [ ] Edit app.py hybrid inference
6. [ ] Restart app.py
7. [ ] Test /scan

**Issue**: Torch CPU DLL dependency missing (likely VC++ Redist 2015-2022 x64 https://aka.ms/vs/17/release/vc_redist.x64.exe). sentence_transformers loads torch → fail.

**Workaround**: Install VC redist, or TF-IDF only upgrade (rename vectorizer to tfidf, train TF-IDF).

**Status**: BERT test partial (torch OK 2.10, sentence_transformers tokenizers OK, no model backend).

Proceed with TF-IDF only? Or user install redist.
