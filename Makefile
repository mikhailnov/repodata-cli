BINDIR ?= /usr/bin

install:
	mkdir -p $(DESTDIR)$(BINDIR)
	install -m 755 repodata.php $(DESTDIR)$(BINDIR)/repodata-cli

test:
	./repodata.php get-package-version test-repo/repodata/repomd.xml dos2unix
