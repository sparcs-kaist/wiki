FROM mediawiki:1.35

# Install Extensions
WORKDIR extensions
RUN git clone --single-branch --branch REL1_35 "https://gerrit.wikimedia.org/r/mediawiki/extensions/Echo.git" Echo \
 && git clone --single-branch --branch REL1_35 "https://gerrit.wikimedia.org/r/mediawiki/extensions/MobileFrontend.git" MobileFrontend

COPY extensions/SSOClient SSOClient

# Install Skins
WORKDIR ../skins
RUN git clone "https://github.com/jthingelstad/foreground.git" Foreground \
 && git clone "https://gitlab.com/librewiki/Liberty-MW-Skin.git" Liberty \
 && cd Liberty && git checkout c6008ea2bee084b17c4aa052f10c1abfb1449b67 \
 && cd ../Foreground && git checkout 9539a175e8d5796f8f778f437d4093a6aa3aab18 \
 && cd ..

# All done!
WORKDIR ..
RUN chown -R www-data images
